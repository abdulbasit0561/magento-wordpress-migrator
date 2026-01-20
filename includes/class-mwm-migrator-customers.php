<?php
/**
 * Customer Migrator Class
 *
 * Handles migrating customers from Magento to WordPress via Connector
 *
 * @package Magento_WordPress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWM_Migrator_Customers {

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
            'failed' => 0
        );
        error_log('MWM Customers Migrator: Using Connector mode');
    }

    /**
     * Run the customer migration
     *
     * @return array Statistics
     */
    public function run() {
        try {
            error_log('MWM Customers: ============================================');
            error_log('MWM Customers: Starting customer migration via Connector');

            MWM_Logger::log_migration_start('customers');

            // Get total count
            error_log('MWM Customers: Fetching customer count from connector...');
            $total_count = $this->connector->get_customers_count();

            if (is_wp_error($total_count)) {
                error_log('MWM Customers: ERROR getting count - ' . $total_count->get_error_message());
                $this->stats['total'] = 0;
            } else {
                $this->stats['total'] = max(1, intval($total_count));
                error_log('MWM Customers: Total customers: ' . $this->stats['total']);
            }

            MWM_Logger::log('info', 'customer_import_count', '', sprintf(
                __('Found %d customers to migrate', 'magento-wordpress-migrator'),
                $this->stats['total']
            ));

            // Update migration progress
            $this->update_progress(__('Starting customer migration...', 'magento-wordpress-migrator'));

            // Process in batches with pagination
            $page = 1;
            $consecutive_empty_batches = 0;
            $max_empty_batches = 3;
            
            // Calculate max pages based on total count
            $max_pages = ($this->stats['total'] > 0) 
                ? ceil($this->stats['total'] / $this->batch_size) + 5  // Add buffer for safety
                : 500;

            while ($page <= $max_pages) {
                error_log("MWM Customers: Fetching page $page (batch_size: {$this->batch_size})...");

                $result = $this->connector->get_customers($this->batch_size, $page);

                // Handle WP_Error responses
                if (is_wp_error($result)) {
                    error_log('MWM Customers: WP ERROR fetching customers: ' . $result->get_error_message());
                    $consecutive_empty_batches++;
                    if ($consecutive_empty_batches >= $max_empty_batches) {
                        error_log('MWM Customers: Too many consecutive errors, stopping migration');
                        break;
                    }
                    $page++;
                    continue;
                }

                // Validate response structure
                if (!is_array($result)) {
                    error_log('MWM Customers: Invalid response format (not an array)');
                    $consecutive_empty_batches++;
                    if ($consecutive_empty_batches >= $max_empty_batches) {
                        error_log('MWM Customers: Too many consecutive invalid responses, stopping migration');
                        break;
                    }
                    $page++;
                    continue;
                }

                error_log('MWM Customers: Connector response keys: ' . implode(', ', array_keys($result)));
                
                // Extract customers from response
                $customers = isset($result['customers']) && is_array($result['customers']) 
                    ? $result['customers'] 
                    : array();

                $batch_count = count($customers);
                error_log("MWM Customers: Retrieved {$batch_count} customers from page {$page}");

                // Check if we got an empty batch
                if ($batch_count === 0) {
                    $consecutive_empty_batches++;
                    error_log("MWM Customers: Empty batch ($consecutive_empty_batches/$max_empty_batches)");
                    
                    if ($consecutive_empty_batches >= $max_empty_batches) {
                        error_log('MWM Customers: Reached max empty batches, stopping migration');
                        break;
                    }
                    $page++;
                    continue;
                }

                // Reset consecutive empty batches counter on successful batch
                $consecutive_empty_batches = 0;

                // Process each customer in the batch
                foreach ($customers as $customer) {
                    // Check if migration is cancelled
                    $migration_data = get_option('mwm_current_migration', array());
                    if (isset($migration_data['status']) && $migration_data['status'] === 'cancelled') {
                        error_log('MWM Customers: Migration cancelled by user');
                        return $this->stats;
                    }

                    $this->migrate_customer($customer);
                }

                // Check if we've processed all customers
                if ($this->stats['processed'] >= $this->stats['total']) {
                    error_log("MWM Customers: All {$this->stats['total']} customers processed, stopping migration");
                    break;
                }

                // Move to next page
                $page++;

                // Small delay to prevent server overload
                usleep(100000); // 0.1 seconds
            }

            error_log('MWM Customers: Migration loop completed. Stats: ' . print_r($this->stats, true));
            MWM_Logger::log_migration_complete('customers', $this->stats);

            return $this->stats;

        } catch (Exception $e) {
            error_log('MWM: Customer migration failed with error: ' . $e->getMessage());
            error_log('MWM: Exception trace: ' . $e->getTraceAsString());
            MWM_Logger::log('error', 'customer_migration_error', '', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Migrate single customer
     *
     * @param array $magento_customer Magento customer data
     */
   private function migrate_customer($magento_customer) {
    try {
        $email = $magento_customer['email'] ?? '';

        if (empty($email)) {
            throw new Exception(__('Customer email is missing', 'magento-wordpress-migrator'));
        }

        $this->update_progress(__('Migrating customer:', 'magento-wordpress-migrator') . ' ' . $email);
        
        // Debug: Log what data we're receiving
        error_log("MWM Customers: Processing customer {$email}");
        error_log("MWM Customers: Order data - total_orders_count: " . ($magento_customer['total_orders_count'] ?? 'not set') . ", total_spend: " . ($magento_customer['total_spend'] ?? 'not set'));

        // Get customer addresses (included in connector response)
        $addresses = $magento_customer['addresses'] ?? array();

        // Check if customer already exists
        $existing_user = get_user_by('email', $email);

        $username = $this->generate_username($email, $magento_customer);
        $first_name = $magento_customer['firstname'] ?? '';
        $last_name = $magento_customer['lastname'] ?? '';

        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => wp_generate_password(24, true, true),
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name),
            'role' => 'customer'
        );

        if ($existing_user) {
            // Update existing user
            $user_data['ID'] = $existing_user->ID;
            $user_id = wp_update_user($user_data);

            if (is_wp_error($user_id)) {
                throw new Exception($user_id->get_error_message());
            }

            $action = 'customer_update';

        } else {
            // Create new user
            $user_id = wp_insert_user($user_data);

            if (is_wp_error($user_id)) {
                throw new Exception($user_id->get_error_message());
            }

            $action = 'customer_create';

            // Send password reset email to new users
            wp_new_user_notification($user_id, null, 'both');
        }

        // Store Magento customer ID
        update_user_meta($user_id, '_magento_customer_id', $magento_customer['entity_id']);

        // Store other metadata
        if (isset($magento_customer['website_id'])) {
            update_user_meta($user_id, '_magento_website_id', $magento_customer['website_id']);
        }

        if (isset($magento_customer['store_id'])) {
            update_user_meta($user_id, '_magento_store_id', $magento_customer['store_id']);
        }

        if (isset($magento_customer['created_at'])) {
            update_user_meta($user_id, '_magento_created_at', $magento_customer['created_at']);
        }

        // Store order statistics from sample response
        // Store for WooCommerce display (these are the keys WooCommerce looks for)
        if (isset($magento_customer['total_orders_count'])) {
            $order_count = intval($magento_customer['total_orders_count']);
            update_user_meta($user_id, '_order_count', $order_count); // WooCommerce key for order count
            update_user_meta($user_id, '_magento_total_orders_count', $order_count); // Backup key
            error_log("MWM Customers: Set _order_count to {$order_count} for user {$user_id}");
        } else {
            error_log("MWM Customers: total_orders_count not found for user {$user_id}");
        }

        if (isset($magento_customer['total_spend'])) {
            $total_spend = floatval($magento_customer['total_spend']);
            update_user_meta($user_id, '_money_spent', $total_spend); // WooCommerce key for total spend
            update_user_meta($user_id, '_magento_total_spend', $total_spend); // Backup key
            error_log("MWM Customers: Set _money_spent to {$total_spend} for user {$user_id}");
        } else {
            error_log("MWM Customers: total_spend not found for user {$user_id}");
        }

        // Store additional order metrics for reference
        if (isset($magento_customer['base_total_spend'])) {
            update_user_meta($user_id, '_magento_base_total_spend', floatval($magento_customer['base_total_spend']));
        }

        if (isset($magento_customer['net_spend'])) {
            update_user_meta($user_id, '_magento_net_spend', floatval($magento_customer['net_spend']));
        }

        if (isset($magento_customer['completed_orders'])) {
            update_user_meta($user_id, '_magento_completed_orders', $magento_customer['completed_orders']);
        }

        if (isset($magento_customer['processing_orders'])) {
            update_user_meta($user_id, '_magento_processing_orders', $magento_customer['processing_orders']);
        }

        if (isset($magento_customer['cancelled_orders'])) {
            update_user_meta($user_id, '_magento_cancelled_orders', $magento_customer['cancelled_orders']);
        }

        if (isset($magento_customer['closed_orders'])) {
            update_user_meta($user_id, '_magento_closed_orders', $magento_customer['closed_orders']);
        }

        if (isset($magento_customer['valid_orders'])) {
            update_user_meta($user_id, '_magento_valid_orders', $magento_customer['valid_orders']);
        }

        if (isset($magento_customer['total_refunded'])) {
            update_user_meta($user_id, '_magento_total_refunded', floatval($magento_customer['total_refunded']));
        }

        if (isset($magento_customer['has_refunds'])) {
            update_user_meta($user_id, '_magento_has_refunds', $magento_customer['has_refunds'] ? 'yes' : 'no');
        }

        if (isset($magento_customer['average_order_value'])) {
            update_user_meta($user_id, '_magento_average_order_value', floatval($magento_customer['average_order_value']));
        }

        if (isset($magento_customer['max_order_value'])) {
            update_user_meta($user_id, '_magento_max_order_value', floatval($magento_customer['max_order_value']));
        }

        if (isset($magento_customer['min_order_value'])) {
            update_user_meta($user_id, '_magento_min_order_value', floatval($magento_customer['min_order_value']));
        }

        if (isset($magento_customer['first_order_date'])) {
            update_user_meta($user_id, '_magento_first_order_date', $magento_customer['first_order_date']);
        }

        if (isset($magento_customer['last_order_date'])) {
            update_user_meta($user_id, '_magento_last_order_date', $magento_customer['last_order_date']);
        }

        if (isset($magento_customer['is_returning_customer'])) {
            update_user_meta($user_id, '_magento_is_returning_customer', $magento_customer['is_returning_customer'] ? 'yes' : 'no');
        }

        if (isset($magento_customer['days_since_last_order'])) {
            update_user_meta($user_id, '_magento_days_since_last_order', $magento_customer['days_since_last_order']);
        }

        // Migrate addresses
        if (!empty($addresses)) {
            $this->migrate_customer_addresses($user_id, $addresses);
        }

        $this->stats['successful']++;
        $this->stats['processed']++;
        $this->update_progress(__('Migrated:', 'magento-wordpress-migrator') . ' ' . $email);
        $this->update_stats();

        MWM_Logger::log_success($action, $email, sprintf(
            __('Customer migrated successfully: %s', 'magento-wordpress-migrator'),
            $email
        ));

    } catch (Exception $e) {
        $this->stats['failed']++;
        $this->stats['processed']++;
        $this->update_stats();
        $this->add_error($magento_customer['email'] ?? $magento_customer['entity_id'], $e->getMessage());

        MWM_Logger::log_error('customer_import_failed', $magento_customer['email'] ?? $magento_customer['entity_id'], $e->getMessage());
    }
}

    /**
     * Generate unique username
     *
     * @param string $email Customer email
     * @param array $magento_customer Magento customer data
     * @return string Username
     */
    private function generate_username($email, $magento_customer) {
        // Try email prefix first
        $username = sanitize_user(current(explode('@', $email)));

        // Check if username exists
        if (!username_exists($username)) {
            return $username;
        }

        // Try firstname.lastname
        $firstname = $magento_customer['firstname'] ?? '';
        $lastname = $magento_customer['lastname'] ?? '';

        if (!empty($firstname) && !empty($lastname)) {
            $username = sanitize_user($firstname . '.' . $lastname);
            if (!username_exists($username)) {
                return $username;
            }
        }

        // Try with ID
        $username = sanitize_user('customer_' . $magento_customer['entity_id']);
        if (!username_exists($username)) {
            return $username;
        }

        // Add number until unique
        $i = 1;
        $base_username = $username;
        while (username_exists($username)) {
            $username = $base_username . $i;
            $i++;
        }

        return $username;
    }

    /**
     * Migrate customer addresses
     *
     * @param int $user_id User ID
     * @param array $addresses Addresses
     */
 private function migrate_customer_addresses($user_id, $addresses) {
    $billing_address_set = false;
    $shipping_address_set = false;
    
    // First pass: set default addresses
    foreach ($addresses as $address) {
        if (isset($address['default_billing']) && $address['default_billing']) {
            $this->set_user_address($user_id, $address, 'billing');
            $billing_address_set = true;
        }
        
        if (isset($address['default_shipping']) && $address['default_shipping']) {
            $this->set_user_address($user_id, $address, 'shipping');
            $shipping_address_set = true;
        }
    }
    
    // Second pass: fill in missing addresses if not set
    foreach ($addresses as $address) {
        $is_default_billing = isset($address['default_billing']) && $address['default_billing'];
        $is_default_shipping = isset($address['default_shipping']) && $address['default_shipping'];
        
        if (!$billing_address_set && !$is_default_billing && !$is_default_shipping) {
            $this->set_user_address($user_id, $address, 'billing');
            $billing_address_set = true;
        } elseif (!$shipping_address_set && !$is_default_billing && !$is_default_shipping) {
            $this->set_user_address($user_id, $address, 'shipping');
            $shipping_address_set = true;
        }
    }
    
    // If still no billing address, use first address as billing
    if (!$billing_address_set && !empty($addresses)) {
        $this->set_user_address($user_id, $addresses[0], 'billing');
    }
    
    // If still no shipping address, copy from billing
    if (!$shipping_address_set) {
        $this->copy_billing_to_shipping($user_id);
    }
}

/**
 * Set user address
 *
 * @param int $user_id User ID
 * @param array $address Address data
 * @param string $type Address type (billing/shipping)
 */
private function set_user_address($user_id, $address, $type) {
    $prefix = $type . '_';
    
    $address_fields = array(
        'first_name' => $address['firstname'] ?? '',
        'last_name' => $address['lastname'] ?? '',
        'company' => $address['company'] ?? '',
        'address_1' => $this->format_street_line($address['street'] ?? '', 0),
        'address_2' => $this->format_street_line($address['street'] ?? '', 1),
        'city' => $address['city'] ?? '',
        'postcode' => $address['postcode'] ?? '',
        'country' => $this->format_country_code($address['country_id'] ?? ''),
        'state' => $address['region'] ?? '',
        'phone' => $address['telephone'] ?? '',
        'email' => '' // Will be populated from user data
    );

    // Update user meta
    foreach ($address_fields as $key => $value) {
        update_user_meta($user_id, $prefix . $key, $value);
    }

    // Store Magento address ID
    if (isset($address['entity_id'])) {
        update_user_meta($user_id, $prefix . '_magento_address_id', $address['entity_id']);
    }
    
    // Log the address type that was set
    error_log("MWM Customers: Set {$type} address for user {$user_id}");
}

/**
 * Copy billing address to shipping address
 *
 * @param int $user_id User ID
 */
private function copy_billing_to_shipping($user_id) {
    $fields = array(
        'first_name', 'last_name', 'company', 'address_1', 'address_2',
        'city', 'postcode', 'country', 'state', 'phone', 'email'
    );
    
    foreach ($fields as $field) {
        $billing_value = get_user_meta($user_id, 'billing_' . $field, true);
        update_user_meta($user_id, 'shipping_' . $field, $billing_value);
    }
    
    // Also copy the Magento address ID if exists
    $billing_magento_id = get_user_meta($user_id, 'billing__magento_address_id', true);
    if ($billing_magento_id) {
        update_user_meta($user_id, 'shipping__magento_address_id', $billing_magento_id);
    }
    
    error_log("MWM Customers: Copied billing to shipping for user {$user_id}");
}

/**
 * Format street line
 *
 * @param mixed $street Street data
 * @param int $line Line number (0 or 1)
 * @return string Formatted street line
 */
private function format_street_line($street, $line) {
    if (is_array($street)) {
        return $street[$line] ?? '';
    }
    
    // If street is string and we need line 0, return the whole string
    if ($line === 0 && is_string($street)) {
        return $street;
    }
    
    return '';
}

/**
 * Format country code
 *
 * @param string $country_code Country code
 * @return string Formatted country code
 */
private function format_country_code($country_code) {
    // Ensure country code is uppercase
    return strtoupper($country_code);
}

/**
 * Get address prefix (billing_ or shipping_)
 * [Deprecated - use set_user_address instead]
 *
 * @param array $address Address data
 * @return string Prefix
 */
private function get_address_prefix($address) {
    // Default to billing if default_billing is true
    if (isset($address['default_billing']) && $address['default_billing']) {
        return 'billing_';
    }

    // Default to shipping if default_shipping is true
    if (isset($address['default_shipping']) && $address['default_shipping']) {
        return 'shipping_';
    }

    // Default to billing
    return 'billing_';
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
        $migration_data['errors'][] = array(
            'item' => $item,
            'message' => $error
        );
        update_option('mwm_current_migration', $migration_data);
    }
}