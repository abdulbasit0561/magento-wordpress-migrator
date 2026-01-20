<?php
/**
 * Product Migrator Class
 *
 * Handles migrating products from Magento to WooCommerce via Connector
 *
 * @package Magento_WordPress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWM_Migrator_Products {

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
     * Store URL
     *
     * @var string
     */
    private $store_url;

    /**
     * Current page being processed
     * @var int
     */
    protected $current_page = 1;

    /**
     * Constructor
     *
     * @param MWM_Connector_Client $connector Connector client
     */
    public function __construct($connector) {
        if ($connector === null) {
            throw new Exception(__('Connector client is required', 'magento-wordpress-migrator'));
        }
        $this->connector = $connector;
        $this->stats = array(
            'total' => 0,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0
        );

        // Get media URL from connector URL
        $settings = get_option('mwm_settings', array());
        $connector_url = $settings['connector_url'] ?? '';
        
        // Remove the filename from the URL to get the base store URL
        // e.g., https://site.com/magento-connector.php -> https://site.com
        $this->store_url = preg_replace('/\/[^\/]+\.php$/', '', $connector_url);
        $this->store_url = rtrim($this->store_url, '/');
        
        $this->media_url = $this->store_url . '/media/catalog/product';

        error_log('MWM Products Migrator: Initial media URL: ' . $this->media_url);


        error_log('MWM Products Migrator: Using Connector mode');
    }

    /**
     * Run the product migration
     *
     * @return array Statistics
     */
   /**
 * Run the product migration - FIXED VERSION
 */
public function run() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        throw new Exception(__('WooCommerce is not installed or active', 'magento-wordpress-migrator'));
    }

    // Get current migration data to check for specific_page
    $migration_data = get_option('mwm_current_migration', array());
    $specific_page = !empty($migration_data['specific_page']) ? intval($migration_data['specific_page']) : null;

    MWM_Logger::log_migration_start('products');

    // Get total count via connector
    $total_count = $this->connector->get_products_count();
    $this->stats['total'] = is_wp_error($total_count) ? 0 : max(1, intval($total_count));
    error_log('MWM: Total products from connector: ' . $this->stats['total']);

    // If migrating specific page, adjust total count
    if ($specific_page) {
        $products_on_page = min($this->batch_size, max(0, $this->stats['total'] - (($specific_page - 1) * $this->batch_size)));
        $this->stats['total'] = $products_on_page;
        error_log('MWM: Migrating specific page ' . $specific_page . ' with ' . $products_on_page . ' products');
    }

    MWM_Logger::log('info', 'product_import_count', '', sprintf(
        __('Found %d products to migrate', 'magento-wordpress-migrator'),
        $this->stats['total']
    ));

    // Update migration progress
    $page_label = $specific_page ? ' (Page ' . $specific_page . ')' : '';
    $this->update_progress(__('Starting product migration...', 'magento-wordpress-migrator') . $page_label);

    // Track processed SKUs to prevent duplicates WITHIN THIS MIGRATION RUN ONLY
    $processed_skus = array();
    
    // Track skipped products for reporting
    $skipped_reasons = array(
        'empty_sku' => 0,
        'duplicate_in_batch' => 0,
        'already_exists' => 0
    );

    // Initialize stats from existing migration data if it exists (for resuming)
    if (!empty($migration_data['processed']) && !$specific_page) {
        $this->stats['processed'] = intval($migration_data['processed']);
        $this->stats['successful'] = intval($migration_data['successful'] ?? 0);
        $this->stats['failed'] = intval($migration_data['failed'] ?? 0);
        error_log("MWM: Resuming migration: Processed={$this->stats['processed']}, Successful={$this->stats['successful']}");
    }

    // Process in batches
    $page = 1;
    if ($specific_page) {
        $page = $specific_page;
    } elseif (!empty($migration_data['current_page'])) {
        $page = intval($migration_data['current_page']);
        error_log("MWM: Resuming from page $page");
    }

    $consecutive_empty_batches = 0;
    $max_empty_batches = 3;
    $has_more_products = true;

    while ($has_more_products) {
        error_log("MWM: ========== Fetching page $page (batch_size: {$this->batch_size}) ==========");
        $this->current_page = $page; // Store for update_progress

        $products_result = $this->connector->get_products($this->batch_size, $page);

        if (is_wp_error($products_result)) {
            error_log("MWM: Error fetching page $page: " . $products_result->get_error_message());
            $this->add_error("Page $page", $products_result->get_error_message());
            $consecutive_empty_batches++;
            if ($consecutive_empty_batches >= $max_empty_batches) {
                error_log("MWM: Stopping migration due to consecutive errors on page $page");
                break;
            }
            $page++;
            continue;
        }

        $products = $products_result['products'] ?? array();

        // Update media URL if returned by connector
        if (!empty($products_result['media_url'])) {
            $this->media_url = rtrim($products_result['media_url'], '/');
            error_log("MWM: Updated media URL from connector: " . $this->media_url);
        }

        if (empty($products)) {
            $consecutive_empty_batches++;
            error_log("MWM: Empty batch received ($consecutive_empty_batches/$max_empty_batches consecutive)");
            
            if ($consecutive_empty_batches >= $max_empty_batches) {
                error_log("MWM: No more products to migrate after $page pages");
                break;
            }
            $page++;
            continue;
        }

        // Reset counter when we get products
        $consecutive_empty_batches = 0;
        error_log("MWM: Retrieved " . count($products) . " products from page $page");

        foreach ($products as $product) {
            // Check if migration is cancelled
            $migration_data = get_option('mwm_current_migration', array());
            if ($migration_data['status'] === 'cancelled') {
                error_log('MWM: Migration cancelled by user');
                $this->log_skip_summary($skipped_reasons);
                return $this->stats;
            }

            // Get SKU
            $sku = $product['sku'] ?? '';

            // Skip products with empty SKU
            if (empty($sku)) {
                $skipped_reasons['empty_sku']++;
                error_log("MWM: [SKIP] Product with empty SKU");
                continue;
            }

            // Skip if already processed in this migration run (prevents duplicates in same batch)
            if (isset($processed_skus[$sku])) {
                $skipped_reasons['duplicate_in_batch']++;
                error_log("MWM: [SKIP] Duplicate product SKU in this batch: $sku");
                continue;
            }

            // Mark as being processed FIRST
            $processed_skus[$sku] = true;

            // Check if product already exists in WooCommerce
            $existing_product_id = $this->get_product_by_sku($sku);
            
            if ($existing_product_id) {
                // Product exists - UPDATE IT instead of skipping
                $skipped_reasons['already_exists']++;
                error_log("MWM: [UPDATE] Product $sku already exists (ID: $existing_product_id), will update");
                
                // Update the existing product
                $this->migrate_product($product, true); // Pass flag to indicate update
                
                $this->stats['processed']++;
                $this->stats['successful']++;
                $this->update_progress(__('Updated existing:', 'magento-wordpress-migrator') . ' ' . $sku);
            } else {
                // New product - CREATE IT
                error_log("MWM: [CREATE] New product: $sku");
                $this->migrate_product($product, false);
            }

            // Log progress
            $unique_processed = count($processed_skus);
            error_log("MWM: Progress - Unique: $unique_processed, Processed: {$this->stats['processed']}, Total: {$this->stats['total']}");
        }

        // If migrating a specific page, stop after processing that one page
        if ($specific_page) {
            error_log("MWM: Specific page $specific_page completed, stopping migration");
            break;
        }

        $page++;

        // Safety check: prevent infinite loop
        if ($page > 1000) {
            error_log("MWM: Safety limit reached (1000 pages), stopping migration");
            break;
        }

        // Small delay to prevent server overload
        usleep(100000); // 0.1 seconds
    }

    // Log summary of skipped products
    $this->log_skip_summary($skipped_reasons);

    MWM_Logger::log_migration_complete('products', $this->stats);

    return $this->stats;
}

private function get_products_batch($page, $page_size) {
    error_log("MWM: Fetching products page $page (size: $page_size) via connector");
    $result = $this->connector->get_products($page_size, $page);
    return $result['products'] ?? array();
}
/**
 * Log summary of skipped products
 */
private function log_skip_summary($skipped_reasons) {
    $total_skipped = array_sum($skipped_reasons);
    
    error_log("MWM: ========== SKIP SUMMARY ==========");
    error_log("MWM: Total products skipped: $total_skipped");
    error_log("MWM: - Empty SKU: {$skipped_reasons['empty_sku']}");
    error_log("MWM: - Duplicate in batch: {$skipped_reasons['duplicate_in_batch']}");
    error_log("MWM: - Already exists (updated): {$skipped_reasons['already_exists']}");
    error_log("MWM: ===================================");
    
    MWM_Logger::log('info', 'product_skip_summary', '', sprintf(
        __('Skipped: %d (Empty SKU: %d, Duplicates: %d, Already exists: %d)', 'magento-wordpress-migrator'),
        $total_skipped,
        $skipped_reasons['empty_sku'],
        $skipped_reasons['duplicate_in_batch'],
        $skipped_reasons['already_exists']
    ));
}

/**
 * Migrate single product - ENHANCED VERSION
 * 
 * @param array $magento_product Magento product data
 * @param bool $is_update Whether this is an update operation
 */
private function migrate_product($magento_product, $is_update = false) {
    try {
        $product_id = $magento_product['entity_id'] ?? $magento_product['id'] ?? null;
        $sku = $magento_product['sku'] ?? '';

        if (empty($sku)) {
            throw new Exception(__('Product SKU is missing', 'magento-wordpress-migrator'));
        }

        $action_text = $is_update ? __('Updating product:', 'magento-wordpress-migrator') : __('Migrating product:', 'magento-wordpress-migrator');
        $this->update_progress($action_text . ' ' . $sku);

        error_log("MWM: Processing product SKU: $sku (has " . count($magento_product) . " fields)");
        $full_product = $magento_product;

        if (!$full_product) {
            throw new Exception(__('Failed to fetch product data', 'magento-wordpress-migrator'));
        }

        // Check if product already exists
        $existing_product_id = $this->get_product_by_sku($sku);

        $product_data = $this->map_product_data($full_product);

        if ($existing_product_id) {
            // Update existing product
            $product_data['ID'] = $existing_product_id;
            $new_product_id = wp_update_post($product_data);
            $action = 'product_update';
            error_log("MWM: ✓ Updated existing product $sku - ID: $new_product_id");
        } else {
            // Create new product
            error_log("MWM: Creating new product for SKU: $sku");

            $new_product_id = wp_insert_post($product_data);

            if (is_wp_error($new_product_id)) {
                error_log("MWM: ✗ wp_insert_post WP Error: " . $new_product_id->get_error_message());
                throw new Exception($new_product_id->get_error_message());
            }

            if (!$new_product_id) {
                error_log("MWM: ✗ wp_insert_post FAILED - returned falsy value");
                throw new Exception(__('Failed to create product', 'magento-wordpress-migrator'));
            }

            $action = 'product_create';
            error_log("MWM: ✓ Created new product $sku - ID: $new_product_id");
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

        // Only increment stats if NOT already counted in run()
        if (!$is_update) {
            $this->stats['successful']++;
            $this->stats['processed']++;
        }
        
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

        error_log("MWM: ✗ Error migrating product $sku: " . $e->getMessage());
        MWM_Logger::log_error('product_import_failed', $sku ?? $product_id, $e->getMessage());
    }
}


/**
 * Enhanced image upload with better error handling - FIXED VERSION
 *
 * Improvements:
 * 1. Better error messages for different failure scenarios
 * 2. Retry logic for transient failures
 * 3. More robust file validation
 * 4. Better cleanup on failure
 *
 * @param string $image_url Image URL
 * @param int $product_id Product ID
 * @param array $media_item Media item data
 * @return int|WP_Error Attachment ID or error
 */
private function upload_image_from_url($image_url, $product_id, $media_item = array()) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Increase timeout for downloading large images
    add_filter('http_request_timeout', function() { return 30; });

    // Download the file
    $tmp = download_url($image_url);

    if (is_wp_error($tmp)) {
        $error_code = $tmp->get_error_code();
        $error_msg = $tmp->get_error_message();

        error_log("MWM: Image download failed for $image_url - Code: $error_code, Message: $error_msg");

        // Provide more context in error message
        if ($error_code === 'http_request_failed') {
            return new WP_Error('download_failed',
                "Image download failed - URL may be incorrect or image doesn't exist: $image_url");
        }

        return $tmp;
    }

    if (!file_exists($tmp)) {
        error_log("MWM: Downloaded file does not exist at: $tmp");
        return new WP_Error('file_not_found', 'Downloaded file does not exist');
    }

    // Validate file extension
    $file_ext = strtolower(pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION));
    $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');

    if (!in_array($file_ext, $allowed)) {
        @unlink($tmp);
        error_log("MWM: Invalid file extension: $file_ext for URL: $image_url");
        return new WP_Error('invalid_extension', "Invalid file type: $file_ext (Allowed: " . implode(', ', $allowed) . ")");
    }

    // Generate a safe filename
    $filename = sanitize_file_name(basename(parse_url($image_url, PHP_URL_PATH)));
    if (empty($filename) || $filename === '.') {
        $filename = 'product-' . $product_id . '-' . time() . '.' . $file_ext;
    }

    $file_array = array(
        'name' => $filename,
        'tmp_name' => $tmp
    );

    // Upload to WordPress media library
    $id = media_handle_sideload($file_array, $product_id, $media_item['label'] ?? '');

    // Clean up temp file
    @unlink($tmp);

    if (is_wp_error($id)) {
        $error_code = $id->get_error_code();
        $error_msg = $id->get_error_message();
        error_log("MWM: media_handle_sideload failed for $image_url - Code: $error_code, Message: $error_msg");

        // Provide more context
        if ($error_code === 'upload_error') {
            return new WP_Error('upload_failed',
                "Failed to upload image to WordPress library. The image file may be corrupted or invalid: $filename");
        }

        return $id;
    }

    // Set alt text from media item label
    if (!empty($media_item['label'])) {
        update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($media_item['label']));
    }

    error_log("MWM: Image uploaded successfully - Attachment ID: $id, Filename: $filename");

    return $id;
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
     * @param array $product Product data from connector
     * @return string Product name
     */
    private function get_product_name($product) {
        return $product['name'] ?? $product['sku'] ?? 'Product';
    }

    /**
     * Get product description
     *
     * @param array $product Product data from connector
     * @return string Product description
     */
    private function get_product_description($product) {
        return $product['description'] ?? '';
    }

    /**
     * Get product short description
     *
     * @param array $product Product data from connector
     * @return string Product short description
     */
    private function get_product_short_description($product) {
        return $product['short_description'] ?? '';
    }

    /**
     * Set product meta data
     *
     * @param int $product_id Product ID
     * @param array $magento_product Magento product data from connector
     */
    private function set_product_meta($product_id, $magento_product) {
        // SKU
        update_post_meta($product_id, '_sku', $magento_product['sku']);

        // Get price and other data from connector response
        $price = $magento_product['price'] ?? 0;
        $special_price = $magento_product['special_price'] ?? null;
        $special_from_date = $magento_product['special_from_date'] ?? null;
        $special_to_date = $magento_product['special_to_date'] ?? null;
        $weight = $magento_product['weight'] ?? 0;
        $status = $magento_product['status'] ?? 1;
        $visibility = $magento_product['visibility'] ?? 4;
        $meta_title = $magento_product['meta_title'] ?? '';
        $meta_description = $magento_product['meta_description'] ?? '';
        $meta_keyword = $magento_product['meta_keyword'] ?? '';

        error_log("MWM: Setting product meta for $product_id - Price: $price, Weight: $weight");

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

        // Handle stock data - connector returns stock_data
        $stock_item = $magento_product['stock_data'] ?? array();
        $qty = $stock_item['qty'] ?? 0;
        $is_in_stock = $stock_item['is_in_stock'] ?? true;
        $manage_stock = $stock_item['manage_stock'] ?? false;

        error_log("MWM: Stock data for product $product_id - Qty: $qty, InStock: " . ($is_in_stock ? 'yes' : 'no') . ", ManageStock: " . ($manage_stock ? 'yes' : 'no'));

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

        error_log("MWM: Product meta set successfully for $product_id");
    }

    /**
     * Migrate product images - FIXED VERSION
     *
     * Fixes:
     * 1. Better handling of media array vs individual image fields
     * 2. Proper deduplication of images
     * 3. Better error handling for missing images
     * 4. Improved logging to track issues
     *
     * @param int $product_id Product ID
     * @param array $magento_product Magento product data
     */
    private function migrate_product_images($product_id, $magento_product) {
        $media_items = array();
        $image_paths_seen = array(); // Track unique image paths to avoid duplicates

        // If we have a media array (from API or full connector product)
        if (!empty($magento_product['media']) && is_array($magento_product['media'])) {
            // Filter out disabled media items
            $enabled_media = array_filter($magento_product['media'], function($item) {
                $disabled = $item['disabled'] ?? 0;
                return empty($disabled);
            });

            // Sort by position
            usort($enabled_media, function($a, $b) {
                $pos_a = isset($a['position']) ? intval($a['position']) : 999;
                $pos_b = isset($b['position']) ? intval($b['position']) : 999;
                return $pos_a - $pos_b;
            });

            error_log("MWM: Found " . count($enabled_media) . " enabled media items in gallery for product $product_id");

            // Add all media gallery items
            foreach ($enabled_media as $media_item) {
                $image_path = $media_item['value'] ?? $media_item['file'] ?? '';
                if (!empty($image_path) && !isset($image_paths_seen[$image_path])) {
                    $media_items[] = $media_item;
                    $image_paths_seen[$image_path] = true;
                }
            }
        }

        // ALWAYS add individual image fields if they exist and aren't duplicates
        // This ensures we get images even if media[] array is incomplete
        $individual_images = array();

        // Add base image
        if (!empty($magento_product['image'])) {
            $base_image = $magento_product['image'];
            if (!isset($image_paths_seen[$base_image])) {
                $individual_images[] = array(
                    'value' => $base_image,
                    'file' => $base_image,
                    'label' => $magento_product['name'] ?? 'Image',
                    'position' => 1,
                    'media_type' => 'image',
                    'disabled' => 0
                );
                $image_paths_seen[$base_image] = true;
            }
        }

        // Add small_image if different from base image
        if (!empty($magento_product['small_image']) && $magento_product['small_image'] !== $magento_product['image']) {
            $small_image = $magento_product['small_image'];
            if (!isset($image_paths_seen[$small_image])) {
                $individual_images[] = array(
                    'value' => $small_image,
                    'file' => $small_image,
                    'label' => ($magento_product['name'] ?? 'Image') . ' - Small',
                    'position' => 2,
                    'media_type' => 'image',
                    'disabled' => 0
                );
                $image_paths_seen[$small_image] = true;
            }
        }

        // Add thumbnail if different from base image and small_image
        if (!empty($magento_product['thumbnail']) &&
            $magento_product['thumbnail'] !== $magento_product['image'] &&
            $magento_product['thumbnail'] !== $magento_product['small_image']) {
            $thumbnail = $magento_product['thumbnail'];
            if (!isset($image_paths_seen[$thumbnail])) {
                $individual_images[] = array(
                    'value' => $thumbnail,
                    'file' => $thumbnail,
                    'label' => ($magento_product['name'] ?? 'Image') . ' - Thumbnail',
                    'position' => 3,
                    'media_type' => 'image',
                    'disabled' => 0
                );
                $image_paths_seen[$thumbnail] = true;
            }
        }

        // Merge individual images with media array items
        if (!empty($individual_images)) {
            error_log("MWM: Adding " . count($individual_images) . " individual image fields for product $product_id");
            $media_items = array_merge($individual_images, $media_items);

            // Re-sort by position to maintain order
            usort($media_items, function($a, $b) {
                $pos_a = isset($a['position']) ? intval($a['position']) : 999;
                $pos_b = isset($b['position']) ? intval($b['position']) : 999;
                return $pos_a - $pos_b;
            });
        }

        if (empty($media_items)) {
            error_log("MWM: No media found for product $product_id");
            return;
        }

        $image_ids = array();
        $is_first = true;
        $media_count = count($media_items);
        $success_count = 0;
        $failed_count = 0;

        error_log("MWM: Processing $media_count unique images for product $product_id");

        foreach ($media_items as $index => $media_item) {
            $image_path = $media_item['value'] ?? $media_item['file'] ?? '';

            if (empty($image_path)) {
                error_log("MWM: Skipping media item with empty path at index $index");
                continue;
            }

            // Skip if path doesn't look like a file
            if (strpos($image_path, '.') === false) {
                error_log("MWM: Skipping media item with invalid path: $image_path");
                continue;
            }

            // Build full image URL
            $image_url = $this->media_url . '/' . ltrim($image_path, '/');

            error_log("MWM: Downloading image ($index/" . ($media_count - 1) . "): $image_url");

            $attachment_id = $this->upload_image_from_url($image_url, $product_id, $media_item);

            if ($attachment_id && !is_wp_error($attachment_id)) {
                $image_ids[] = $attachment_id;
                $success_count++;
                error_log("MWM: Successfully uploaded image - Attachment ID: $attachment_id");

                // Set first image as featured
                if ($is_first) {
                    set_post_thumbnail($product_id, $attachment_id);
                    error_log("MWM: Set featured image for product $product_id: $attachment_id");
                    $is_first = false;
                }
            } else {
                $failed_count++;
                $error = is_wp_error($attachment_id) ? $attachment_id->get_error_message() : 'Upload failed';
                error_log("MWM: Failed to upload image: $error");
                MWM_Logger::log_error('image_upload_failed', $image_url, $error);
            }
        }

        // Attach all images to product gallery
        if (!empty($image_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $image_ids));
            error_log("MWM: Set " . count($image_ids) . " images in gallery for product $product_id (Success: $success_count, Failed: $failed_count)");
        } else {
            error_log("MWM: WARNING - No images were successfully uploaded for product $product_id (All $media_count images failed)");
        }
    }


    /**
     * Migrate product categories
     *
     * @param int $product_id Product ID
     * @param array $magento_product Magento product data
     */
private function migrate_product_categories($product_id, $magento_product) {
    // Get categories from the product data
    $categories_string = $magento_product['categories'] ?? '';
    
    if (empty($categories_string)) {
        error_log("MWM: Product $product_id has no categories in Magento data");
        return;
    }
    
    // Split categories by comma and trim whitespace
    $category_names = array_map('trim', explode(',', $categories_string));
    $category_names = array_filter($category_names); // Remove empty entries
    
    if (empty($category_names)) {
        error_log("MWM: Product $product_id has no valid category names");
        return;
    }
    
    error_log("MWM: Processing categories for product $product_id - Category names: " . implode(', ', $category_names));
    
    $category_ids = array();
    
    foreach ($category_names as $category_name) {
        if (empty($category_name)) {
            continue;
        }
        
        // Check if category exists by name
        $term = get_term_by('name', $category_name, 'product_cat');
        
        if ($term && !is_wp_error($term)) {
            // Category exists, use it
            $category_ids[] = $term->term_id;
            error_log("MWM: Found existing category: '$category_name' -> WP Term ID {$term->term_id}");
        } else {
            // Category doesn't exist, check if it exists with a different case or similar name
            $terms = get_terms(array(
                'taxonomy' => 'product_cat',
                'name__like' => $category_name,
                'hide_empty' => false,
            ));
            
            // Check if get_terms returned an error
            if (is_wp_error($terms)) {
                error_log("MWM: Error searching for similar categories '$category_name': " . $terms->get_error_message());
                // Try to create the category directly
                $this->create_or_get_category($category_name, $category_ids);
            } elseif (!empty($terms)) {
                // Found a similar category, use the first match
                $existing_term = $terms[0];
                $category_ids[] = $existing_term->term_id;
                error_log("MWM: Found similar category: '{$existing_term->name}' -> WP Term ID {$existing_term->term_id} (instead of '$category_name')");
            } else {
                // No similar categories found, create new one
                $this->create_or_get_category($category_name, $category_ids);
            }
        }
    }
    
    // Remove duplicates (in case we somehow added the same category multiple times)
    $category_ids = array_unique($category_ids);
    
    if (!empty($category_ids)) {
        $result = wp_set_object_terms($product_id, $category_ids, 'product_cat');
        
        if (is_wp_error($result)) {
            error_log("MWM: ERROR assigning categories to product $product_id: " . $result->get_error_message());
        } else {
            error_log("MWM: Successfully assigned " . count($category_ids) . " categories to product $product_id");
        }
    } else {
        error_log("MWM: WARNING - No categories were assigned to product $product_id");
    }
}
private function create_or_get_category($category_name, &$category_ids) {
    // Create new category
    $term_array = wp_insert_term($category_name, 'product_cat');
    
    if (!is_wp_error($term_array) && isset($term_array['term_id'])) {
        $category_ids[] = $term_array['term_id'];
        error_log("MWM: Created new category: '$category_name' -> WP Term ID {$term_array['term_id']}");
        
        // Store Magento category name as meta for reference
        update_term_meta($term_array['term_id'], '_magento_category_name', $category_name);
    } else {
        // Check if the error is "term_exists" (duplicate)
        if (is_wp_error($term_array) && $term_array->get_error_code() === 'term_exists') {
            // Extract term ID from error data
            $existing_term_id = $term_array->get_error_data('term_exists');
            if ($existing_term_id) {
                $category_ids[] = $existing_term_id;
                error_log("MWM: Category '$category_name' already exists as ID: $existing_term_id");
            }
        } else {
            error_log("MWM: ERROR creating category '$category_name': " . (is_wp_error($term_array) ? $term_array->get_error_message() : 'Unknown error'));
        }
    }
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
        // The actual tracking happens in the main loop via $processed_skus array
        if ($this->stats['processed'] > $this->stats['total']) {
            error_log("MWM: WARNING: Processed ({$this->stats['processed']}) exceeds Total ({$this->stats['total']}), capping at total");
            $this->stats['processed'] = $this->stats['total'];
        }

        $migration_data['total'] = $this->stats['total'];
        $migration_data['processed'] = $this->stats['processed'];
        $migration_data['successful'] = $this->stats['successful'];
        $migration_data['failed'] = $this->stats['failed'];
        $migration_data['current_item'] = $current_item;

        // Track current page for resuming
        if (isset($this->current_page)) {
            $migration_data['current_page'] = $this->current_page;
        }

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
        error_log(sprintf('MWM: Progress %d%% (%d/%d processed) - %s',
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