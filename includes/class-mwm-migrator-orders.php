<?php
/**
 * Order Migrator Class
 *
 * Handles migrating orders from Magento to WooCommerce via Connector
 *
 * @package Magento_WordPress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWM_Migrator_Orders {

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
            'failed' => 0,
            'skipped' => 0 // Added skipped counter
        );
        error_log('MWM Orders Migrator: Using Connector mode');
    }

    /**
     * Run the order migration
     *
     * @return array Statistics
     */
    public function run() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            throw new Exception(__('WooCommerce is not installed or active', 'magento-wordpress-migrator'));
        }

        MWM_Logger::log_migration_start('orders');

        // Get total count via connector
        $total_count = $this->connector->get_orders_count();
        if (is_wp_error($total_count)) {
            throw new Exception('Failed to get orders count: ' . $total_count->get_error_message());
        }
        $this->stats['total'] = max(0, intval($total_count));

        // Log actual count from Magento
        error_log('MWM Orders: Magento reports ' . $this->stats['total'] . ' orders');

        // If no orders, return early
        if ($this->stats['total'] === 0) {
            return $this->stats;
        }

        MWM_Logger::log('info', 'order_import_count', '', sprintf(
            __('Found %d orders to migrate', 'magento-wordpress-migrator'),
            $this->stats['total']
        ));

        // Update migration progress
        $this->update_progress(__('Starting order migration...', 'magento-wordpress-migrator'));

        // Clear any previous errors
        $this->clear_errors();

        // Process in batches with pagination
        $page = 1;
        $max_pages = ceil($this->stats['total'] / $this->batch_size) + 2; // Safety buffer
        $processed_ids = array(); // Track processed Magento IDs to prevent duplicates

        error_log('MWM Orders: Starting import with max pages: ' . $max_pages);

        while ($page <= $max_pages && $this->stats['processed'] < $this->stats['total']) {
            // Check if migration is cancelled
            $migration_data = get_option('mwm_current_migration', array());
            if (isset($migration_data['status']) && $migration_data['status'] === 'cancelled') {
                error_log('MWM Orders: Migration cancelled by user');
                return $this->stats;
            }

            $this->update_progress(sprintf(__('Fetching batch %d/%d...', 'magento-wordpress-migrator'), $page, $max_pages));

            $result = $this->connector->get_orders($this->batch_size, $page);

            if (is_wp_error($result)) {
                throw new Exception('Failed to fetch orders: ' . $result->get_error_message());
            }

            $orders = isset($result['orders']) ? $result['orders'] : array();

            if (empty($orders)) {
                error_log('MWM Orders: No more orders found at page ' . $page);
                break;
            }

            error_log('MWM Orders: Processing batch ' . $page . ' with ' . count($orders) . ' orders');

            foreach ($orders as $order) {
                $order_id = $order['entity_id'] ?? 0;
                
                // Skip if already processed in this session (prevents duplicates in same run)
                if (in_array($order_id, $processed_ids)) {
                    error_log('MWM Orders: Skipping duplicate order ID in same batch: ' . $order_id);
                    continue;
                }
                
                $processed_ids[] = $order_id;
                $this->migrate_order($order);
                
                // Safety check: if we've processed more than total, something is wrong
                if ($this->stats['processed'] > $this->stats['total']) {
                    error_log('MWM Orders: WARNING: Processed count (' . $this->stats['processed'] . ') exceeds total (' . $this->stats['total'] . ')');
                    break 2; // Exit both loops
                }
            }

            $page++;

            // Small delay to prevent server overload
            usleep(100000); // 0.1 seconds
        }

        // Final validation
        $this->validate_import_count();

        MWM_Logger::log_migration_complete('orders', $this->stats);
        error_log('MWM Orders: ============================================ COMPLETE');
        error_log('MWM Orders: Final stats - Total: ' . $this->stats['total'] . 
                  ', Processed: ' . $this->stats['processed'] . 
                  ', Successful: ' . $this->stats['successful'] . 
                  ', Failed: ' . $this->stats['failed'] . 
                  ', Skipped: ' . $this->stats['skipped']);

        return $this->stats;
    }

    /**
     * Migrate single order
     *
     * @param array $magento_order Magento order data from connector
     */
    private function migrate_order($magento_order) {
        try {
            $order_id = $magento_order['entity_id'];
            $increment_id = $magento_order['increment_id'] ?? $order_id;

            $this->update_progress(__('Migrating order:', 'magento-wordpress-migrator') . ' #' . $increment_id);

            // Check if order already exists - Check both by Magento ID and Increment ID
            $existing_order_id = $this->get_order_by_magento_data($order_id, $increment_id);

            if ($existing_order_id) {
                $this->stats['skipped']++;
                $this->stats['processed']++;
                $this->update_stats();
                
                MWM_Logger::log('info', 'order_skipped', $increment_id, sprintf(
                    __('Order already exists: #%s', 'magento-wordpress-migrator'),
                    $increment_id
                ));
                
                error_log('MWM Orders: Skipping existing order #' . $increment_id . ' (Magento ID: ' . $order_id . ')');
                return; // Skip this order
            }

            // Get order addresses and items from connector response
            $billing_address = $magento_order['billing_address'] ?? null;
            $shipping_address = $magento_order['shipping_address'] ?? null;
            $order_items = $magento_order['items'] ?? array();

            // Map order status
            $status = $this->map_order_status($magento_order['state'] ?? 'pending', $magento_order['status'] ?? 'pending');

            // Get customer
            $customer_id = $this->get_customer_id($magento_order);

            // Parse created_at date from Magento
            $created_at = $this->parse_magento_date($magento_order['created_at'] ?? '');
            $updated_at = $this->parse_magento_date($magento_order['updated_at'] ?? '');

            // Temporarily remove date filters to allow setting custom date
            remove_filter('wp_insert_post_data', 'wc_check_post_lock', 10);
            remove_filter('wp_insert_post_data', 'wc_remove_date_save', 10);

            // Create order manually to ensure dates are set correctly before first save
            $order = new WC_Order();
            $order->set_status($status);
            $order->set_customer_id($customer_id);
            $order->set_created_via('magento_migrator');
            $order->set_customer_note($magento_order['customer_note'] ?? '');

            // Set the dates on the order object BEFORE first save
            if ($created_at) {
                $order->set_date_created($created_at);
            }

            if ($updated_at) {
                $order->set_date_modified($updated_at);
            }

            // Save for the first time
            $order->save();


            // Set order currency and totals
            $order->set_currency($magento_order['order_currency_code'] ?? 'USD');
            $order->set_total(floatval($magento_order['grand_total'] ?? 0));

            // Set billing address
            if ($billing_address) {
                $this->set_order_address($order, $billing_address, 'billing');
            }

            // Set shipping address
            if ($shipping_address) {
                $this->set_order_address($order, $shipping_address, 'shipping');
            }

            // Add order items
            if (!empty($order_items)) {
                $this->add_order_items($order, $order_items);
            }

            // If there's a shipping amount, add it as a shipping item
            $shipping_amount = floatval($magento_order['shipping_amount'] ?? 0);
            if ($shipping_amount > 0) {
                $shipping_item = new WC_Order_Item_Shipping();
                $shipping_item->set_order_id($order->get_id());
                $shipping_item->set_method_title('Magento Shipping');
                $shipping_item->set_total($shipping_amount);
                $order->add_item($shipping_item);
            }

            // Store original Magento totals as meta for reference
            $order->update_meta_data('_magento_subtotal', floatval($magento_order['subtotal'] ?? 0));
            $order->update_meta_data('_magento_discount', floatval($magento_order['discount_amount'] ?? 0));
            $order->update_meta_data('_magento_tax', floatval($magento_order['tax_amount'] ?? 0));
            $order->update_meta_data('_magento_shipping', floatval($magento_order['shipping_amount'] ?? 0));

            // Store original Magento dates
            if ($magento_order['created_at'] ?? false) {
                $order->update_meta_data('_magento_created_at', $magento_order['created_at']);
            }
            if ($magento_order['updated_at'] ?? false) {
                $order->update_meta_data('_magento_updated_at', $magento_order['updated_at']);
            }

            // Set payment method
            if (isset($magento_order['payment'])) {
                $order->set_payment_method($magento_order['payment']['method'] ?? 'magento');
                $order->set_payment_method_title($magento_order['payment']['method_title'] ?? __('Magento Payment', 'magento-wordpress-migrator'));
            }

            // Add order note with original date
            $order_note = sprintf(
                __('Migrated from Magento Order #%s', 'magento-wordpress-migrator'),
                $increment_id
            );
            
            if ($magento_order['created_at'] ?? false) {
                $formatted_date = $this->format_date_for_display($magento_order['created_at']);
                $order_note .= ' (' . sprintf(__('Originally created: %s', 'magento-wordpress-migrator'), $formatted_date) . ')';
            }
            
            $order->add_order_note($order_note);

            // Store Magento order IDs using WC CRUD method (HPOS compatible)
            $order->update_meta_data('_magento_order_id', $order_id);
            $order->update_meta_data('_magento_increment_id', $increment_id);

            // Save order with all changes
            $order->save();

            // Re-add the filters we removed
            add_filter('wp_insert_post_data', 'wc_check_post_lock', 10, 2);
            add_filter('wp_insert_post_data', 'wc_remove_date_save', 10, 2);

            $this->stats['successful']++;
            $this->stats['processed']++;
            $this->update_progress(__('Migrated:', 'magento-wordpress-migrator') . ' #' . $increment_id);
            $this->update_stats();

            MWM_Logger::log_success('order_create', $increment_id, sprintf(
                __('Order migrated successfully: #%s (Original date: %s)', 'magento-wordpress-migrator'),
                $increment_id,
                $magento_order['created_at'] ?? 'N/A'
            ));

            error_log('MWM Orders: Successfully migrated order #' . $increment_id . 
                     ' ID: ' . $order->get_id() . 
                     ' with date: ' . ($magento_order['created_at'] ?? 'N/A'));

        } catch (Exception $e) {
            // Re-add filters if they were removed
            add_filter('wp_insert_post_data', 'wc_check_post_lock', 10, 2);
            add_filter('wp_insert_post_data', 'wc_remove_date_save', 10, 2);
            
            $this->stats['failed']++;
            $this->stats['processed']++;
            $this->update_stats();
            $this->add_error($magento_order['increment_id'] ?? $magento_order['entity_id'], $e->getMessage());

            MWM_Logger::log_error('order_import_failed', $magento_order['increment_id'] ?? $magento_order['entity_id'], $e->getMessage());
            error_log('MWM Orders: Failed to migrate order #' . ($magento_order['increment_id'] ?? $magento_order['entity_id']) . ': ' . $e->getMessage());
        }
    }

    /**
     * Parse Magento date string to DateTime object
     *
     * @param string $date_string Magento date string
     * @return DateTime|false DateTime object or false on failure
     */
    private function parse_magento_date($date_string) {
        if (empty($date_string)) {
            return false;
        }

        try {
            // Magento dates are typically in format: 'YYYY-MM-DD HH:MM:SS'
            // or ISO 8601: 'YYYY-MM-DDTHH:MM:SS+00:00'
            
            // Try common Magento date formats
            $formats = [
                'Y-m-d H:i:s',
                'Y-m-d\TH:i:s',
                'Y-m-d\TH:i:sP',
                'Y-m-d\TH:i:s.uP',
                DateTime::ATOM,
                DateTime::ISO8601,
            ];
            
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $date_string);
                if ($date !== false) {
                    return $date;
                }
            }
            
            // Last try with generic parsing
            return new DateTime($date_string);
            
        } catch (Exception $e) {
            error_log('MWM Orders: Failed to parse date: ' . $date_string . ' - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Format date for display
     *
     * @param string $date_string Magento date string
     * @return string Formatted date
     */
    private function format_date_for_display($date_string) {
        $date = $this->parse_magento_date($date_string);
        if ($date) {
            return $date->format('F j, Y g:i A');
        }
        return $date_string;
    }

    /**
     * Get order by Magento ID or Increment ID
     *
     * @param int $magento_id Magento order ID
     * @param string $increment_id Magento increment ID
     * @return int|false Order ID or false
     */
    private function get_order_by_magento_data($magento_id, $increment_id) {
        // Use wc_get_orders for HPOS compatibility
        $orders = wc_get_orders(array(
            'limit' => 1,
            'return' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_magento_order_id',
                    'value' => $magento_id,
                    'compare' => '='
                )
            )
        ));

        if (!empty($orders)) {
            return (int) reset($orders);
        }

        // Also check by Increment ID as fallback
        $orders = wc_get_orders(array(
            'limit' => 1,
            'return' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_magento_increment_id',
                    'value' => $increment_id,
                    'compare' => '='
                )
            )
        ));

        return !empty($orders) ? (int) reset($orders) : false;
    }


    /**
     * Map Magento order status to WooCommerce status
     *
     * @param string $state Order state
     * @param string $status Order status
     * @return string WooCommerce status
     */
    private function map_order_status($state, $status) {
        $status_map = array(
            'new' => 'pending',
            'pending_payment' => 'pending',
            'processing' => 'processing',
            'complete' => 'completed',
            'closed' => 'completed',
            'canceled' => 'cancelled',
            'holded' => 'on-hold',
            'payment_review' => 'on-hold',
        );

        $wc_status = $status_map[$state] ?? 'pending';

        // Ensure status exists in WooCommerce
        $wc_statuses = wc_get_order_statuses();
        if (!isset($wc_statuses['wc-' . $wc_status])) {
            $wc_status = 'pending';
        }

        return $wc_status;
    }

    /**
     * Get customer ID for order
     *
     * @param array $magento_order Magento order data
     * @return int Customer ID or 0 for guest
     */
    private function get_customer_id($magento_order) {
        $email = $magento_order['customer_email'] ?? '';
        $firstname = $magento_order['customer_firstname'] ?? '';
        $lastname = $magento_order['customer_lastname'] ?? '';
        $fullname = trim($firstname . ' ' . $lastname);
        $magento_customer_id = $magento_order['customer_id'] ?? 0;

        // 1. Search by email (most accurate/unique in WordPress)
        if (!empty($email)) {
            $user = get_user_by('email', $email);
            if ($user) {
                error_log("MWM Orders: Linked customer by email: $email (ID: $user->ID)");
                return $user->ID;
            }
        }

        // 2. Search by exact full name (Display Name)
        if (!empty($fullname)) {
            $users = get_users(array(
                'search' => $fullname,
                'search_columns' => array('display_name'),
                'number' => 1
            ));
            
            if (!empty($users)) {
                error_log("MWM Orders: Linked customer by display name: $fullname (ID: " . $users[0]->ID . ")");
                return $users[0]->ID;
            }

            // 3. Search by first and last name meta
            $users = get_users(array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'first_name',
                        'value' => $firstname,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'last_name',
                        'value' => $lastname,
                        'compare' => '='
                    )
                ),
                'number' => 1
            ));
            
            if (!empty($users)) {
                error_log("MWM Orders: Linked customer by name meta: $fullname (ID: " . $users[0]->ID . ")");
                return $users[0]->ID;
            }
        }

        // 4. Fallback: Search by Magento Customer ID if meta exists (last resort)
        if ($magento_customer_id > 0) {
            $users = get_users(array(
                'meta_key' => '_magento_customer_id',
                'meta_value' => $magento_customer_id,
                'number' => 1
            ));

            if (!empty($users)) {
                error_log("MWM Orders: Linked customer by legacy Magento ID: $magento_customer_id (ID: " . $users[0]->ID . ")");
                return $users[0]->ID;
            }
        }

        error_log("MWM Orders: No matching customer found for $email / $fullname. Treating as guest.");
        return 0; // Guest order
    }

    /**
     * Set order address
     *
     * @param WC_Order $order WooCommerce order
     * @param array $address Address data
     * @param string $type Address type (billing or shipping)
     */
    private function set_order_address($order, $address, $type) {
        $prefix = $type; // 'billing' or 'shipping'

        // Use WooCommerce's public setter methods
        $method_map = array(
            'first_name' => 'set_' . $prefix . '_first_name',
            'last_name' => 'set_' . $prefix . '_last_name',
            'company' => 'set_' . $prefix . '_company',
            'address_1' => 'set_' . $prefix . '_address_1',
            'address_2' => 'set_' . $prefix . '_address_2',
            'city' => 'set_' . $prefix . '_city',
            'postcode' => 'set_' . $prefix . '_postcode',
            'country' => 'set_' . $prefix . '_country',
            'state' => 'set_' . $prefix . '_state',
        );

        $address_data = array(
            'first_name' => $address['firstname'] ?? '',
            'last_name' => $address['lastname'] ?? '',
            'company' => $address['company'] ?? '',
            'address_1' => $this->format_street($address['street'] ?? ''),
            'address_2' => '',
            'city' => $address['city'] ?? '',
            'postcode' => $address['postcode'] ?? '',
            'country' => $address['country_id'] ?? '',
            'state' => $address['region'] ?? '',
        );

        foreach ($address_data as $key => $value) {
            if (method_exists($order, $method_map[$key])) {
                $order->{$method_map[$key]}($value);
            }
        }

        // Set phone and email only for billing
        if ($type === 'billing') {
            $order->set_billing_phone($address['telephone'] ?? '');
            $order->set_billing_email($address['email'] ?? '');
        }
    }

    /**
     * Add order items
     *
     * @param WC_Order $order WooCommerce order
     * @param array $order_items Order items
     */
    private function add_order_items($order, $order_items) {
        foreach ($order_items as $item_data) {
            $product_name = $item_data['name'] ?? '';
            $product_sku = $item_data['sku'] ?? '';
            
            // 1. Search by exact product name in WordPress (as requested)
            $product_id = $this->get_product_id_by_name($product_name);
            
            // 2. Fallback to SKU if name search fails
            if (!$product_id && !empty($product_sku)) {
                $product_id = $this->get_product_id_by_sku($product_sku);
            }

            if ($product_id) {
                $item = new WC_Order_Item_Product();
                $item->set_order_id($order->get_id());
                $item->set_product_id($product_id);
                $item->set_name($item_data['name'] ?? 'Product');
                $item->set_quantity(intval($item_data['qty_ordered'] ?? 1));

                // Set line total
                $line_total = floatval($item_data['row_total'] ?? $item_data['price'] ?? 0);
                $item->set_total($line_total);
                $item->set_subtotal($line_total);

                $order->add_item($item);
            } else {
                // Product not found, add as line item without product
                $item = new WC_Order_Item_Product();
                $item->set_order_id($order->get_id());
                $item->set_name($item_data['name'] ?? 'Unknown Product');
                $item->set_quantity(intval($item_data['qty_ordered'] ?? 1));

                $line_total = floatval($item_data['row_total'] ?? $item_data['price'] ?? 0);
                $item->set_total($line_total);
                $item->set_subtotal($line_total);

                $order->add_item($item);
            }
        }
    }

    /**
     * Get product ID by SKU
     *
     * @param string $sku Product SKU
     * @return int|false Product ID or false
     */
    private function get_product_id_by_sku($sku) {
        if (empty($sku)) {
            return false;
        }

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
     * Get product ID by Name
     *
     * @param string $name Product name
     * @return int|false Product ID or false
     */
    private function get_product_id_by_name($name) {
        if (empty($name)) {
            return false;
        }

        global $wpdb;

        // Exact name match via SQL for best performance and accuracy
        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'product' LIMIT 1",
                $name
            )
        );

        if ($product_id) {
            error_log("MWM Orders: Linked product by name: $name (ID: $product_id)");
            return (int) $product_id;
        }

        return false;
    }

    /**
     * Format street address
     *
     * @param string $street Street address
     * @return string Formatted address
     */
    private function format_street($street) {
        if (is_array($street)) {
            return implode("\n", $street);
        }

        return $street;
    }

    /**
     * Update migration progress
     *
     * @param string $current_item Current item being processed
     */
    private function update_progress($current_item = '') {
        $migration_data = get_option('mwm_current_migration', array());
        $migration_data['total'] = $this->stats['total'];
        $migration_data['processed'] = $this->stats['processed'];
        $migration_data['successful'] = $this->stats['successful'];
        $migration_data['failed'] = $this->stats['failed'];
        $migration_data['skipped'] = $this->stats['skipped'] ?? 0;
        $migration_data['current_item'] = $current_item;
        update_option('mwm_current_migration', $migration_data);
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
        if (!isset($migration_data['errors'])) {
            $migration_data['errors'] = array();
        }
        $migration_data['errors'][] = array(
            'item' => $item,
            'message' => $error
        );
        update_option('mwm_current_migration', $migration_data);
    }

    /**
     * Clear previous errors
     */
    private function clear_errors() {
        $migration_data = get_option('mwm_current_migration', array());
        $migration_data['errors'] = array();
        update_option('mwm_current_migration', $migration_data);
    }

    /**
     * Validate import count
     */
    private function validate_import_count() {
        global $wpdb;
        
        // Count total migrated orders
        $migrated_count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_magento_order_id'"
        );
        
        error_log('MWM Orders: Validation - Magento reported: ' . $this->stats['total'] . ', Actually migrated: ' . $migrated_count);
        
        if ($migrated_count > $this->stats['total']) {
            error_log('MWM Orders: WARNING: More orders migrated (' . $migrated_count . ') than reported by Magento (' . $this->stats['total'] . ')');
        }
    }
}