<?php
/**
 * Product Migrator Class - FIXED VERSION
 *
 * Fixes for migration hanging at 48%:
 * 1. Fixed stock_data vs stock_item field name mismatch
 * 2. Fixed duplicate counting causing premature stopping
 * 3. Better handling of existing products
 * 4. Improved pagination logic
 *
 * @package Magento_WordPress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWM_Migrator_Products_Fixed {

    /**
     * Database connection
     *
     * @var MWM_DB
     */
    private $db;

    /**
     * API connector
     *
     * @var MWM_API_Connector
     */
    private $api;

    /**
     * Connector client
     *
     * @var MWM_Connector_Client
     */
    private $connector;

    /**
     * Migration statistics
     *
     * @var array
     */
    private $stats;

    /**
     * Batch size
     *
     * @var int
     */
    private $batch_size = 20;

    /**
     * Media base URL
     *
     * @var string
     */
    private $media_url;

    /**
     * Use API mode
     *
     * @var bool
     */
    private $use_api;

    /**
     * Use connector mode
     *
     * @var bool
     */
    private $use_connector;

    /**
     * Store URL
     *
     * @var string
     */
    private $store_url;

    /**
     * Constructor
     *
     * @param MWM_DB|null $db Database connection (optional if using API)
     * @param MWM_API_Connector|null $api API connector (optional if using DB)
     * @param MWM_Connector_Client|null $connector Connector client (optional)
     */
    public function __construct($db = null, $api = null, $connector = null) {
        $this->db = $db;
        $this->api = $api;
        $this->connector = $connector;
        $this->use_api = ($api !== null);
        $this->use_connector = ($connector !== null);
        $this->stats = array(
            'total' => 0,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0
        );

        // Get media URL
        if ($this->use_connector && $this->connector) {
            $settings = get_option('mwm_settings', array());
            $this->store_url = rtrim($settings['connector_url'], '/');
            $this->media_url = $this->store_url . '/media/catalog/product';
        } elseif ($this->use_api && $this->api) {
            $settings = get_option('mwm_settings', array());
            $this->store_url = rtrim($settings['store_url'], '/');
            $this->media_url = $this->store_url . '/media/catalog/product';
        } elseif ($this->db) {
            $this->store_url = $this->db->get_media_url();
            $this->media_url = $this->store_url;
        }

        $mode = $this->use_connector ? 'Connector mode' : ($this->use_api ? 'API mode' : 'DB mode');
        error_log('MWM Products Migrator (FIXED): Using ' . $mode);
    }

    /**
     * Run the product migration
     *
     * @return array Statistics
     */
    public function run() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            throw new Exception(__('WooCommerce is not installed or active', 'magento-wordpress-migrator'));
        }

        MWM_Logger::log_migration_start('products');

        // Get total count
        if ($this->use_connector) {
            // Get total count via connector
            $total_count = $this->connector->get_products_count();
            $this->stats['total'] = is_wp_error($total_count) ? 0 : max(1, intval($total_count));
            error_log('MWM (FIXED): Total products from connector: ' . $this->stats['total']);
        } elseif ($this->use_api) {
            // Get total count via API
            $this->stats['total'] = max(1, intval($this->api->get_total_count('/products/search')));
            error_log('MWM (FIXED): Total products from API: ' . $this->stats['total']);
        } else {
            // Get total count via DB
            $this->stats['total'] = max(1, intval($this->db->get_total_products()));
        }

        MWM_Logger::log('info', 'product_import_count', '', sprintf(
            __('Found %d products to migrate', 'magento-wordpress-migrator'),
            $this->stats['total']
        ));

        // Update migration progress
        $this->update_progress(__('Starting product migration...', 'magento-wordpress-migrator'));

        // Track processed SKUs to prevent duplicates
        $processed_skus = array();
        $new_products_created = 0; // Track only NEW products created
        $existing_products_found = 0; // Track existing products

        // Process in batches
        $page = 1;
        $consecutive_empty_batches = 0;
        $max_empty_batches = 3;
        $max_pages = 1000; // Safety limit

        // Track if we should continue
        $has_more_products = true;

        error_log('MWM (FIXED): Starting migration loop with batch_size=' . $this->batch_size);

        while ($has_more_products && $page <= $max_pages) {
            error_log("MWM (FIXED): Fetching page $page...");

            $products = $this->get_products_batch($page, $this->batch_size);

            if (empty($products)) {
                $consecutive_empty_batches++;
                error_log("MWM (FIXED): Empty batch $consecutive_empty_batches/$max_empty_batches on page $page");

                // Stop after 3 consecutive empty batches
                if ($consecutive_empty_batches >= $max_empty_batches) {
                    error_log("MWM (FIXED): No more products after $page pages (3+ empty batches)");
                    $has_more_products = false;
                    break;
                }
                $page++;
                continue;
            }

            // Reset counter when we get products
            $consecutive_empty_batches = 0;
            error_log("MWM (FIXED): Retrieved " . count($products) . " products from page $page");

            foreach ($products as $product) {
                // Check if migration is cancelled
                $migration_data = get_option('mwm_current_migration', array());
                if ($migration_data['status'] === 'cancelled') {
                    error_log('MWM (FIXED): Migration cancelled by user');
                    return $this->stats;
                }

                // Get SKU
                $sku = $product['sku'] ?? '';

                // Skip products with empty SKU
                if (empty($sku)) {
                    error_log("MWM (FIXED): Skipping product with empty SKU");
                    continue;
                }

                // Skip if already processed in this migration run (prevents duplicates in same batch)
                if (isset($processed_skus[$sku])) {
                    error_log("MWM (FIXED): Skipping duplicate SKU: $sku (already processed in this run)");
                    continue;
                }

                // Mark as being processed
                $processed_skus[$sku] = true;

                // Check if product already exists in WooCommerce
                $existing_product_id = $this->get_product_by_sku($sku);
                if ($existing_product_id) {
                    error_log("MWM (FIXED): Product $sku already exists (ID: $existing_product_id), skipping");
                    $existing_products_found++;
                    $this->stats['processed']++;
                    $this->stats['successful']++;
                    $this->update_progress(__('Skipping existing:', 'magento-wordpress-migrator') . ' ' . $sku);
                    continue;
                }

                // Migrate the product (this will increment stats)
                error_log("MWM (FIXED): Migrating NEW product: $sku");
                $this->migrate_product($product);
                $new_products_created++;

                // Update progress after each product
                $unique_processed = count($processed_skus);
                error_log("MWM (FIXED): Progress - Unique: $unique_processed, New: $new_products_created, Existing: $existing_products_found, Total expected: {$this->stats['total']}");

                // Don't stop based on count - let the pagination/empty batches handle it naturally
                // This fixes the issue where migration would stop prematurely at 48%
            }

            $page++;

            // Small delay to prevent server overload
            usleep(100000); // 0.1 seconds
        }

        // Final stats update
        error_log("MWM (FIXED): Migration complete. Total unique: " . count($processed_skus) . ", New created: $new_products_created, Existing found: $existing_products_found");

        MWM_Logger::log_migration_complete('products', $this->stats);

        return $this->stats;
    }

    /**
     * Get batch of products
     *
     * @param int $page Page number
     * @param int $page_size Page size
     * @return array Products
     */
    private function get_products_batch($page, $page_size) {
        if ($this->use_connector) {
            error_log("MWM (FIXED): Fetching products page $page (size: $page_size) via connector");
            $result = $this->connector->get_products($page_size, $page);
            return $result['products'] ?? array();
        } elseif ($this->use_api) {
            error_log("MWM (FIXED): Fetching products page $page (size: $page_size) via API");
            $result = $this->api->get_products($page, $page_size);
            return $result['items'] ?? array();
        } else {
            return $this->db->get_products(($page - 1) * $page_size, $page_size);
        }
    }

    /**
     * Migrate single product
     *
     * @param array $magento_product Magento product data
     */
    private function migrate_product($magento_product) {
        try {
            $product_id = $magento_product['entity_id'] ?? $magento_product['id'] ?? null;
            $sku = $magento_product['sku'] ?? '';

            if (empty($sku)) {
                throw new Exception(__('Product SKU is missing', 'magento-wordpress-migrator'));
            }

            $this->update_progress(__('Migrating product:', 'magento-wordpress-migrator') . ' ' . $sku);

            // Get full product data from connector, API or DB
            if ($this->use_connector) {
                // FIX: Connector already returns full product data with all fields
                error_log("MWM (FIXED): Using connector data for SKU: $sku");
                $full_product = $magento_product;
            } elseif ($this->use_api) {
                error_log("MWM (FIXED): Fetching full product data via API for SKU: $sku");
                $full_product = $this->get_full_product_from_api($sku);
            } else {
                error_log("MWM (FIXED): Fetching full product data via DB for ID: $product_id");
                $full_product = $this->db->get_product($product_id);
            }

            if (!$full_product) {
                throw new Exception(__('Failed to fetch product data', 'magento-wordpress-migrator'));
            }

            // Check if product already exists (double check)
            $existing_product_id = $this->get_product_by_sku($sku);

            $product_data = $this->map_product_data($full_product);

            if ($existing_product_id) {
                // Update existing product
                $product_data['ID'] = $existing_product_id;
                $new_product_id = wp_update_post($product_data);
                $action = 'product_update';
                error_log("MWM (FIXED): Updated existing product $sku - ID: $new_product_id");
            } else {
                // Create new product
                $new_product_id = wp_insert_post($product_data);
                $action = 'product_create';
                error_log("MWM (FIXED): Created new product $sku - ID: $new_product_id");
            }

            if (is_wp_error($new_product_id)) {
                throw new Exception($new_product_id->get_error_message());
            }

            // Set product meta data
            $this->set_product_meta($new_product_id, $full_product);

            // Handle product images
            $this->migrate_product_images($new_product_id, $full_product);

            // Handle product categories
            $this->migrate_product_categories($new_product_id, $full_product);

            // Handle product attributes
            $this->migrate_product_attributes($new_product_id, $full_product);

            $this->stats['successful']++;
            $this->stats['processed']++;
            $this->update_progress(__('Migrated:', 'magento-wordpress-migrator') . ' ' . $sku);
            $this->update_stats();

            MWM_Logger::log_success($action, $sku, sprintf(
                __('Product migrated successfully: %s', 'magento-wordpress-migrator'),
                $sku
            ));

        } catch (Exception $e) {
            $this->stats['failed']++;
            $this->stats['processed']++;
            $this->update_stats();
            $this->add_error($sku ?? $product_id, $e->getMessage());

            error_log("MWM (FIXED): Error migrating product $sku: " . $e->getMessage());
            MWM_Logger::log_error('product_import_failed', $sku ?? $product_id, $e->getMessage());
        }
    }

    /**
     * Get full product data from API including images and all details
     *
     * @param string $sku Product SKU
     * @return array|false Full product data
     */
    private function get_full_product_from_api($sku) {
        try {
            error_log("MWM (FIXED): API Request - GET /products/$sku");
            $product = $this->api->get_product($sku);

            if (!$product) {
                error_log("MWM (FIXED): API returned false for product $sku");
                return false;
            }

            error_log("MWM (FIXED): Raw API product data for $sku: " . print_r($product, true));

            // Map API response to match database structure
            $mapped = array(
                'entity_id' => $product['id'] ?? null,
                'sku' => $product['sku'] ?? '',
                'name' => $product['name'] ?? '',
                'type_id' => $this->get_product_type_id($product['type_id'] ?? 'simple'),
                'status' => $product['status'] ?? 1,
                'visibility' => $product['visibility'] ?? 4,
                'price' => $product['price'] ?? 0,
                'special_price' => $product['special_price'] ?? null,
                'special_from_date' => $product['special_from_date'] ?? null,
                'special_to_date' => $product['special_to_date'] ?? null,
                'weight' => $product['weight'] ?? 0,
                'description' => $product['description'] ?? '',
                'short_description' => $product['short_description'] ?? '',
                'meta_title' => $product['meta_title'] ?? '',
                'meta_keyword' => $product['meta_keyword'] ?? '',
                'meta_description' => $product['meta_description'] ?? '',
                'stock_item' => $product['extension_attributes']['stock_item'] ?? array(),
                'category_ids' => $product['category_ids'] ?? array(),
                'media_gallery_entries' => $product['media_gallery_entries'] ?? array(),
                'custom_attributes' => $product['custom_attributes'] ?? array(),
            );

            // Add image data
            if (!empty($product['custom_attributes'])) {
                foreach ($product['custom_attributes'] as $attr) {
                    if ($attr['attribute_code'] === 'image') {
                        $mapped['image'] = $attr['value'] ?? null;
                        break;
                    }
                }
            }

            // Build media array for migration
            $mapped['media'] = array();

            // Add base image
            if (!empty($mapped['image'])) {
                $mapped['media'][] = array(
                    'value' => $mapped['image'],
                    'file' => $mapped['image'],
                    'label' => $product['name'] ?? '',
                    'position' => 1,
                    'media_type' => 'image',
                    'disabled' => 0
                );
            }

            // Add media gallery images
            if (!empty($mapped['media_gallery_entries'])) {
                $position = 2;
                foreach ($mapped['media_gallery_entries'] as $gallery_image) {
                    if (empty($gallery_image['disabled'])) {
                        $mapped['media'][] = array(
                            'value' => $gallery_image['file'],
                            'file' => $gallery_image['file'],
                            'label' => $gallery_image['label'] ?? $product['name'] ?? '',
                            'position' => $position++,
                            'media_type' => 'image',
                            'disabled' => $gallery_image['disabled'] ?? 0
                        );
                    }
                }
            }

            // Add small_image and thumbnail as additional media
            foreach ($product['custom_attributes'] ?? array() as $attr) {
                if ($attr['attribute_code'] === 'small_image' && !empty($attr['value'])) {
                    $mapped['media'][] = array(
                        'value' => $attr['value'],
                        'file' => $attr['value'],
                        'label' => $product['name'] . ' - Small',
                        'position' => 999,
                        'media_type' => 'image',
                        'disabled' => 0
                    );
                }
                if ($attr['attribute_code'] === 'thumbnail' && !empty($attr['value'])) {
                    $mapped['media'][] = array(
                        'value' => $attr['value'],
                        'file' => $attr['value'],
                        'label' => $product['name'] . ' - Thumbnail',
                        'position' => 998,
                        'media_type' => 'image',
                        'disabled' => 0
                    );
                }
            }

            error_log("MWM (FIXED): Mapped product data for $sku has " . count($mapped['media']) . " images");

            return $mapped;

        } catch (Exception $e) {
            error_log("MWM (FIXED): Exception fetching product from API: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get WooCommerce product type ID from Magento type
     *
     * @param string $magento_type Magento product type
     * @return string WooCommerce product type
     */
    private function get_product_type_id($magento_type) {
        $type_map = array(
            'simple' => 'simple',
            'virtual' => 'simple',
            'downloadable' => 'simple',
            'configurable' => 'variable',
            'grouped' => 'grouped',
            'bundle' => 'grouped',
        );

        return $type_map[$magento_type] ?? 'simple';
    }

    /**
     * Get product ID by SKU
     *
     * @param string $sku Product SKU
     * @return int|false Product ID or false
     */
    private function get_product_by_sku($sku) {
        global $wpdb;

        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1",
                $sku
            )
        );

        return $product_id ? (int) $product_id : false;
    }

    /**
     * Map Magento product data to WooCommerce format
     *
     * @param array $magento_product Magento product data
     * @return array WooCommerce product data
     */
    private function map_product_data($magento_product) {
        $product_data = array(
            'post_title' => $this->get_product_name($magento_product),
            'post_content' => $this->get_product_description($magento_product),
            'post_excerpt' => $this->get_product_short_description($magento_product),
            'post_status' => 'publish',
            'post_type' => 'product',
            'post_author' => get_current_user_id(),
        );

        return $product_data;
    }

    /**
     * Get product name
     *
     * @param array $product Product data
     * @return string Product name
     */
    private function get_product_name($product) {
        // First try direct field (from connector/API)
        if (isset($product['name']) && !empty($product['name'])) {
            return $product['name'];
        }

        // Then try to get name from attributes (from DB)
        if (isset($product['attributes'])) {
            foreach ($product['attributes'] as $attr) {
                if ($attr['attribute_code'] === 'name') {
                    return $attr['value'];
                }
            }
        }

        return $product['sku'] ?? 'Product';
    }

    /**
     * Get product description
     *
     * @param array $product Product data
     * @return string Product description
     */
    private function get_product_description($product) {
        // First try direct field (from connector/API)
        if (isset($product['description']) && !empty($product['description'])) {
            return $product['description'];
        }

        // Then try to get description from attributes (from DB)
        if (isset($product['attributes'])) {
            foreach ($product['attributes'] as $attr) {
                if ($attr['attribute_code'] === 'description') {
                    return $attr['value'];
                }
            }
        }

        return '';
    }

    /**
     * Get product short description
     *
     * @param array $product Product data
     * @return string Product short description
     */
    private function get_product_short_description($product) {
        // First try direct field (from connector/API)
        if (isset($product['short_description']) && !empty($product['short_description'])) {
            return $product['short_description'];
        }

        // Then try to get short_description from attributes (from DB)
        if (isset($product['attributes'])) {
            foreach ($product['attributes'] as $attr) {
                if ($attr['attribute_code'] === 'short_description') {
                    return $attr['value'];
                }
            }
        }

        return '';
    }

    /**
     * Set product meta data
     *
     * @param int $product_id Product ID
     * @param array $magento_product Magento product data
     */
    private function set_product_meta($product_id, $magento_product) {
        // SKU
        update_post_meta($product_id, '_sku', $magento_product['sku']);

        // Get price and other data - handle both API and DB formats
        $price = 0;
        $special_price = null;
        $special_from_date = null;
        $special_to_date = null;
        $weight = 0;
        $status = 1;
        $visibility = 4;
        $meta_title = '';
        $meta_description = '';
        $meta_keyword = '';

        // If data is from API (has direct price field)
        if (isset($magento_product['price'])) {
            $price = $magento_product['price'];
            $special_price = $magento_product['special_price'] ?? null;
            $special_from_date = $magento_product['special_from_date'] ?? null;
            $special_to_date = $magento_product['special_to_date'] ?? null;
            $weight = $magento_product['weight'] ?? 0;
            $status = $magento_product['status'] ?? 1;
            $visibility = $magento_product['visibility'] ?? 4;
            $meta_title = $magento_product['meta_title'] ?? '';
            $meta_description = $magento_product['meta_description'] ?? '';
            $meta_keyword = $magento_product['meta_keyword'] ?? '';
        }
        // If data is from database (has attributes array)
        elseif (isset($magento_product['attributes']) && is_array($magento_product['attributes'])) {
            foreach ($magento_product['attributes'] as $attr) {
                $attr_code = $attr['attribute_code'] ?? '';
                $attr_value = $attr['value'] ?? '';

                switch ($attr_code) {
                    case 'price':
                        $price = floatval($attr_value);
                        break;
                    case 'special_price':
                        $special_price = floatval($attr_value);
                        break;
                    case 'special_from_date':
                        $special_from_date = $attr_value;
                        break;
                    case 'special_to_date':
                        $special_to_date = $attr_value;
                        break;
                    case 'weight':
                        $weight = floatval($attr_value);
                        break;
                    case 'status':
                        $status = intval($attr_value);
                        break;
                    case 'visibility':
                        $visibility = intval($attr_value);
                        break;
                    case 'meta_title':
                        $meta_title = $attr_value;
                        break;
                    case 'meta_description':
                        $meta_description = $attr_value;
                        break;
                    case 'meta_keyword':
                        $meta_keyword = $attr_value;
                        break;
                }
            }
        }

        error_log("MWM (FIXED): Setting product meta for $product_id - Price: $price, Weight: $weight");

        // Set pricing
        update_post_meta($product_id, '_price', $price);
        update_post_meta($product_id, '_regular_price', $price);

        if ($special_price && floatval($special_price) > 0) {
            update_post_meta($product_id, '_sale_price', floatval($special_price));

            // Set sale price dates
            if ($special_from_date) {
                update_post_meta($product_id, '_sale_price_dates_from', strtotime($special_from_date));
            }
            if ($special_to_date) {
                update_post_meta($product_id, '_sale_price_dates_to', strtotime($special_to_date));
            }
        } else {
            delete_post_meta($product_id, '_sale_price');
        }

        // FIX: Handle stock data - check both stock_item (API) and stock_data (connector)
        $stock_item = $magento_product['stock_data'] ?? $magento_product['stock_item'] ?? array();
        $qty = $stock_item['qty'] ?? 0;
        $is_in_stock = $stock_item['is_in_stock'] ?? true;
        $manage_stock = $stock_item['manage_stock'] ?? false;

        error_log("MWM (FIXED): Stock data - Qty: $qty, InStock: " . ($is_in_stock ? 'yes' : 'no') . ", ManageStock: " . ($manage_stock ? 'yes' : 'no'));

        if ($manage_stock) {
            update_post_meta($product_id, '_stock', $qty);
            update_post_meta($product_id, '_stock_status', $is_in_stock ? 'instock' : 'outofstock');
            update_post_meta($product_id, '_manage_stock', 'yes');
        } else {
            update_post_meta($product_id, '_stock', $is_in_stock ? 100 : 0);
            update_post_meta($product_id, '_stock_status', $is_in_stock ? 'instock' : 'outofstock');
            update_post_meta($product_id, '_manage_stock', 'no');
        }

        // Set weight
        if ($weight > 0) {
            update_post_meta($product_id, '_weight', $weight);
        }

        // Set visibility
        $catalog_visibility = in_array($visibility, array(2, 4)) ? 'visible' : 'hidden';
        update_post_meta($product_id, '_visibility', $catalog_visibility);
        update_post_meta($product_id, '_catalog_visibility', $catalog_visibility);

        // Set featured
        update_post_meta($product_id, '_featured', 'no');

        // Store Magento product ID
        $entity_id = $magento_product['entity_id'] ?? $magento_product['id'];
        update_post_meta($product_id, '_magento_product_id', $entity_id);

        // Store meta information
        if (!empty($meta_title)) {
            update_post_meta($product_id, '_meta_title', $meta_title);
        }
        if (!empty($meta_description)) {
            update_post_meta($product_id, '_meta_description', $meta_description);
        }
        if (!empty($meta_keyword)) {
            update_post_meta($product_id, '_meta_keywords', $meta_keyword);
        }

        // Store custom attributes (API mode)
        if (!empty($magento_product['custom_attributes'])) {
            foreach ($magento_product['custom_attributes'] as $attr) {
                if ($attr['attribute_code'] !== 'image' &&
                    $attr['attribute_code'] !== 'small_image' &&
                    $attr['attribute_code'] !== 'thumbnail' &&
                    $attr['attribute_code'] !== 'special_price' &&
                    $attr['attribute_code'] !== 'special_from_date' &&
                    $attr['attribute_code'] !== 'special_to_date') {
                    $code = 'magento_' . $attr['attribute_code'];
                    $value = $attr['value'] ?? '';
                    update_post_meta($product_id, $code, $value);
                }
            }
        }
        // Store all attributes from database mode
        elseif (!empty($magento_product['attributes'])) {
            foreach ($magento_product['attributes'] as $attr) {
                $attr_code = $attr['attribute_code'] ?? '';
                $attr_value = $attr['value'] ?? '';

                // Skip already processed attributes
                if (!in_array($attr_code, array('price', 'special_price', 'special_from_date', 'special_to_date',
                    'weight', 'status', 'visibility', 'meta_title', 'meta_description', 'meta_keyword',
                    'name', 'description', 'short_description'))) {
                    $code = 'magento_' . $attr_code;
                    update_post_meta($product_id, $code, $attr_value);
                }
            }
        }

        error_log("MWM (FIXED): Product meta set successfully for $product_id");
    }

    /**
     * Migrate product images
     *
     * @param int $product_id Product ID
     * @param array $magento_product Magento product data
     */
    private function migrate_product_images($product_id, $magento_product) {
        $media_items = array();

        // If we have a media array (from API or full connector product)
        if (!empty($magento_product['media'])) {
            $media_items = $magento_product['media'];
        }
        // Otherwise build media array from image fields (from connector list)
        elseif (!empty($magento_product['image'])) {
            // Add base image
            $media_items[] = array(
                'value' => $magento_product['image'],
                'file' => $magento_product['image'],
                'label' => $magento_product['name'] ?? 'Image',
                'position' => 1,
                'media_type' => 'image',
                'disabled' => 0
            );

            // Add small_image if different from base image
            if (!empty($magento_product['small_image']) && $magento_product['small_image'] !== $magento_product['image']) {
                $media_items[] = array(
                    'value' => $magento_product['small_image'],
                    'file' => $magento_product['small_image'],
                    'label' => ($magento_product['name'] ?? 'Image') . ' - Small',
                    'position' => 2,
                    'media_type' => 'image',
                    'disabled' => 0
                );
            }

            // Add thumbnail if different from base image
            if (!empty($magento_product['thumbnail']) && $magento_product['thumbnail'] !== $magento_product['image']) {
                $media_items[] = array(
                    'value' => $magento_product['thumbnail'],
                    'file' => $magento_product['thumbnail'],
                    'label' => ($magento_product['name'] ?? 'Image') . ' - Thumbnail',
                    'position' => 3,
                    'media_type' => 'image',
                    'disabled' => 0
                );
            }
        }

        if (empty($media_items)) {
            error_log("MWM (FIXED): No media found for product $product_id");
            return;
        }

        $image_ids = array();
        $is_first = true;
        $media_count = count($media_items);

        error_log("MWM (FIXED): Processing $media_count images for product $product_id");

        foreach ($media_items as $index => $media_item) {
            $image_path = $media_item['value'] ?? $media_item['file'] ?? '';

            if (empty($image_path)) {
                continue;
            }

            // Build full image URL
            $image_url = $this->media_url . '/' . ltrim($image_path, '/');

            error_log("MWM (FIXED): Downloading image ($index/" . ($media_count - 1) . "): $image_url");

            $attachment_id = $this->upload_image_from_url($image_url, $product_id);

            if ($attachment_id && !is_wp_error($attachment_id)) {
                $image_ids[] = $attachment_id;
                error_log("MWM (FIXED): Successfully uploaded image - Attachment ID: $attachment_id");

                // Set first image as featured
                if ($is_first) {
                    set_post_thumbnail($product_id, $attachment_id);
                    error_log("MWM (FIXED): Set featured image for product $product_id: $attachment_id");
                    $is_first = false;
                }
            } else {
                $error = is_wp_error($attachment_id) ? $attachment_id->get_error_message() : 'Upload failed';
                error_log("MWM (FIXED): Failed to upload image: $error");
                MWM_Logger::log_error('image_upload_failed', $image_url, $error);
            }
        }

        // Attach all images to product gallery
        if (!empty($image_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $image_ids));
            error_log("MWM (FIXED): Set " . count($image_ids) . " images in gallery for product $product_id");
        }
    }

    /**
     * Upload image from URL
     *
     * @param string $image_url Image URL
     * @param int $product_id Product ID
     * @return int|false Attachment ID or false
     */
    private function upload_image_from_url($image_url, $product_id) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Download file
        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            MWM_Logger::log_error('image_download_failed', $image_url, $tmp->get_error_message());
            return false;
        }

        // Get file info
        $file_array = array(
            'name' => basename(parse_url($image_url, PHP_URL_PATH)),
            'tmp_name' => $tmp
        );

        // Upload to WordPress
        $id = media_handle_sideload($file_array, $product_id);

        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            MWM_Logger::log_error('image_upload_failed', $image_url, $id->get_error_message());
            return false;
        }

        return $id;
    }

    /**
     * Migrate product categories
     *
     * @param int $product_id Product ID
     * @param array $magento_product Magento product data
     */
    private function migrate_product_categories($product_id, $magento_product) {
        // Handle both 'categories' and 'category_ids' field names
        $magento_category_ids = $magento_product['category_ids'] ?? $magento_product['categories'] ?? array();

        if (empty($magento_category_ids)) {
            return;
        }

        $category_ids = array();

        foreach ($magento_category_ids as $magento_category_id) {
            // Check if category exists
            $term_id = $this->get_category_by_magento_id($magento_category_id);

            if ($term_id) {
                $category_ids[] = $term_id;
            }
        }

        if (!empty($category_ids)) {
            wp_set_object_terms($product_id, $category_ids, 'product_cat');
        }
    }

    /**
     * Get category by Magento ID
     *
     * @param int $magento_id Magento category ID
     * @return int|false Term ID or false
     */
    private function get_category_by_magento_id($magento_id) {
        $terms = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'meta_key' => '_magento_category_id',
            'meta_value' => $magento_id
        ));

        if (!empty($terms) && !is_wp_error($terms)) {
            return $terms[0]->term_id;
        }

        return false;
    }

    /**
     * Migrate product attributes
     *
     * @param int $product_id Product ID
     * @param array $magento_product Magento product data
     */
    private function migrate_product_attributes($product_id, $magento_product) {
        // For now, attributes are stored as post meta in set_product_meta()
        // This function can be extended to create WooCommerce product attributes
        // if needed in the future
    }

    /**
     * Update migration progress with percentage calculation
     *
     * @param string $current_item Current item being processed
     */
    private function update_progress($current_item = '') {
        $migration_data = get_option('mwm_current_migration', array());

        // CRITICAL: Cap processed at total to prevent >100% progress
        if ($this->stats['processed'] > $this->stats['total']) {
            error_log("MWM (FIXED): WARNING: Processed ({$this->stats['processed']}) exceeds Total ({$this->stats['total']}), capping at total");
            $this->stats['processed'] = $this->stats['total'];
        }

        $migration_data['total'] = $this->stats['total'];
        $migration_data['processed'] = $this->stats['processed'];
        $migration_data['successful'] = $this->stats['successful'];
        $migration_data['failed'] = $this->stats['failed'];
        $migration_data['current_item'] = $current_item;

        // Calculate percentage - always cap at 100%
        $percentage = 0;
        if ($this->stats['total'] > 0) {
            $percentage = min(100, round(($this->stats['processed'] / $this->stats['total']) * 100, 1));
        }
        $migration_data['percentage'] = $percentage;

        // Add estimated time remaining
        if (!empty($migration_data['started']) && $this->stats['processed'] > 0) {
            $started_time = is_numeric($migration_data['started']) ? $migration_data['started'] : strtotime($migration_data['started']);
            $elapsed = time() - $started_time;

            // Only calculate if we have valid elapsed time
            if ($elapsed > 0 && $this->stats['processed'] > 0) {
                $avg_time_per_item = $elapsed / $this->stats['processed'];
                $remaining_items = max(0, $this->stats['total'] - $this->stats['processed']);
                $estimated_seconds = $avg_time_per_item * $remaining_items;

                // Format time remaining - handle edge cases
                if ($estimated_seconds <= 0 || $remaining_items == 0) {
                    // Migration complete or nearly complete
                    $time_remaining = __('Complete', 'magento-wordpress-migrator');
                } elseif ($estimated_seconds < 60) {
                    $time_remaining = ceil($estimated_seconds) . ' ' . __('seconds', 'magento-wordpress-migrator');
                } elseif ($estimated_seconds < 3600) {
                    $minutes = ceil($estimated_seconds / 60);
                    $time_remaining = $minutes . ' ' . _n('minute', 'minutes', $minutes, 'magento-wordpress-migrator');
                } else {
                    $hours = round($estimated_seconds / 3600, 1);
                    $time_remaining = $hours . ' ' . _n('hour', 'hours', $hours, 'magento-wordpress-migrator');
                }
                $migration_data['time_remaining'] = $time_remaining;
            } else {
                $migration_data['time_remaining'] = __('Calculating...', 'magento-wordpress-migrator');
            }
        } else {
            $migration_data['time_remaining'] = __('Calculating...', 'magento-wordpress-migrator');
        }

        update_option('mwm_current_migration', $migration_data);

        // Log progress for debugging
        error_log(sprintf('MWM (FIXED): Progress %d%% (%d/%d processed) - %s',
            $percentage,
            $this->stats['processed'],
            $this->stats['total'],
            $current_item
        ));
    }

    /**
     * Update statistics
     */
    private function update_stats() {
        $this->update_progress();
    }

    /**
     * Add error to migration data
     *
     * @param string $item Item identifier
     * @param string $error Error message
     */
    private function add_error($item, $error) {
        $migration_data = get_option('mwm_current_migration', array());
        $migration_data['errors'][] = array(
            'item' => $item,
            'message' => $error
        );
        update_option('mwm_current_migration', $migration_data);
    }
}
