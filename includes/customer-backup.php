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
            $total_count = $this->get_customer();
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
            $max_pages = 20; // Safety limit
 $result = $this->connector->get_customers();
 $this->migrate_customer($result);
 die('here i am ');
 foreach($result as $customer_data)
 {
       $this->migrate_customer($customer_data);
        // echo (json_encode($customer_data));
 }
 die('dd');
            while ($page <= $max_pages) {
                die($max_pages);
                error_log("MWM Customers: Fetching page $page (batch_size: {$this->batch_size})...");

                $result = $this->connector->get_customers($this->batch_size, $page);

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

                error_log('MWM Customers: Connector response keys: ' . implode(', ', array_keys($result)));
                $customers = isset($result['customers']) ? $result['customers'] : array();

                if (empty($customers)) {
                    $consecutive_empty_batches++;
                    error_log("MWM Customers: Empty batch ($consecutive_empty_batches/$max_empty_batches)");
                    if ($consecutive_empty_batches >= $max_empty_batches) {
                        error_log('MWM Customers: Reached max empty batches, stopping migration');
                        break;
                    }
                    $page++;
                    continue;
                }

                $consecutive_empty_batches = 0;
                error_log("MWM Customers: Retrieved " . count($customers) . " customers from page $page");

                foreach ($customers as $customer) {
                    // Check if migration is cancelled
                    $migration_data = get_option('mwm_current_migration', array());
                    if ($migration_data['status'] === 'cancelled') {
                        error_log('MWM Customers: Migration cancelled by user');
                        return $this->stats;
                    }

                    $this->migrate_customer($customer);
                }

                $page++;

                // Small delay to prevent server overload
                usleep(100000); // 0.1 seconds
            }

            error_log('MWM Customers: Migration loop completed. Stats: ' . print_r($this->stats, true));
            MWM_Logger::log_migration_complete('customers', $this->stats);

            return $this->stats;

        } catch (Exception $e) {
            die('dddd');
            error_log('MWM: Customer migration failed with error: ' . $e->getMessage());
            error_log('MWM: Exception trace: ' . $e->getTraceAsString());
            MWM_Logger::log('error', 'customer_migration_error', '', $e->getMessage());
            throw $e;
        }
    }
    public function get_customer()
    {
        return $total_count = $this->connector->get_customers_count();
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
        foreach ($addresses as $address) {
            $prefix = $this->get_address_prefix($address);

            $address_fields = array(
                'first_name' => $address['firstname'] ?? '',
                'last_name' => $address['lastname'] ?? '',
                'company' => $address['company'] ?? '',
                'address_1' => $this->format_street($address['street'] ?? ''),
                'address_2' => '',
                'city' => $address['city'] ?? '',
                'postcode' => $address['postcode'] ?? '',
                'country' => $address['country_id'] ?? '',
                'state' => $address['region'] ?? '',
                'phone' => $address['telephone'] ?? ''
            );

            // Update user meta
            foreach ($address_fields as $key => $value) {
                update_user_meta($user_id, $prefix . $key, $value);
            }

            // Store Magento address ID
            if (isset($address['entity_id'])) {
                update_user_meta($user_id, $prefix . '_magento_address_id', $address['entity_id']);
            }

            // Store default flags
            if (isset($address['default_shipping'])) {
                update_user_meta($user_id, $prefix . '_default_shipping', $address['default_shipping']);
            }

            if (isset($address['default_billing'])) {
                update_user_meta($user_id, $prefix . '_default_billing', $address['default_billing']);
            }
        }
    }

    /**
     * Get address prefix (billing_ or shipping_)
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
     * Format street address
     *
     * @param string $street Street address
     * @return string Formatted address
     */
    private function format_street($street) {
        // Handle array or string street
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
