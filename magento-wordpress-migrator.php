<?php
/**
 * Plugin Name: Magento to WordPress Migrator
 * Plugin URI: https://github.com/yourusername/magento-wordpress-migrator
 * Description: Migrate products, categories, customers, and orders from Magento to WordPress/WooCommerce. Connects via Magento Connector API for fast and reliable migration.
 * Version: 1.1.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: magento-wordpress-migrator
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 10.4
 * Woo: 9900200-9900299:9900400-9900499
 *
 * @package Magento_WordPress_Migrator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MWM_VERSION', '1.1.0');
define('MWM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MWM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MWM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('MWM_MIN_PHP_VERSION', '7.4');
define('MWM_MIN_WP_VERSION', '5.8');

/**
 * Main Plugin Class
 */
class Magento_WordPress_Migrator {

    /**
     * Single instance of the class
     *
     * @var Magento_WordPress_Migrator
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Magento_WordPress_Migrator
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->check_compatibility();
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Check plugin compatibility
     */
    private function check_compatibility() {
        // Check PHP version
        if (version_compare(PHP_VERSION, MWM_MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), MWM_MIN_WP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            return false;
        }

        // Check if WooCommerce is active - but still load the plugin
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            // Don't return - allow plugin to load for setup
        }

        return true;
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once MWM_PLUGIN_DIR . 'includes/class-mwm-logger.php';
        require_once MWM_PLUGIN_DIR . 'includes/class-mwm-connector-client.php';
        require_once MWM_PLUGIN_DIR . 'includes/class-mwm-migrator-base.php';
        require_once MWM_PLUGIN_DIR . 'includes/class-mwm-migrator-products.php';
        require_once MWM_PLUGIN_DIR . 'includes/class-mwm-migrator-categories.php';
        require_once MWM_PLUGIN_DIR . 'includes/class-mwm-migrator-customers.php';
        require_once MWM_PLUGIN_DIR . 'includes/class-mwm-migrator-orders.php';

        // Admin classes
        require_once MWM_PLUGIN_DIR . 'includes/admin/class-mwm-admin.php';
        require_once MWM_PLUGIN_DIR . 'includes/admin/class-mwm-settings.php';
        require_once MWM_PLUGIN_DIR . 'includes/admin/class-mwm-migration-page.php';

        // Initialize admin
        new MWM_Admin();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_mwm_test_connector', array($this, 'ajax_test_connector'));
        add_action('wp_ajax_mwm_start_migration', array($this, 'ajax_start_migration'));
        add_action('wp_ajax_mwm_get_progress', array($this, 'ajax_get_progress'));
        add_action('wp_ajax_mwm_cancel_migration', array($this, 'ajax_cancel_migration'));
        add_action('wp_ajax_mwm_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_mwm_get_product_pages', array($this, 'ajax_get_product_pages'));
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'magento-wordpress-migrator',
            false,
            dirname(MWM_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'magento-wp-migrator') === false) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'mwm-admin-css',
            MWM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MWM_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'mwm-admin-js',
            MWM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MWM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('mwm-admin-js', 'mwmAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mwm_ajax_nonce'),
            'strings' => array(
                'migrating' => __('Migrating...', 'magento-wordpress-migrator'),
                'completed' => __('Completed', 'magento-wordpress-migrator'),
                'error' => __('Error', 'magento-wordpress-migrator'),
                'confirm_cancel' => __('Are you sure you want to cancel the migration?', 'magento-wordpress-migrator'),
                'connection_failed' => __('Connection failed. Please check your credentials.', 'magento-wordpress-migrator'),
                'connection_success' => __('Connection successful!', 'magento-wordpress-migrator')
            )
        ));
    }

    /**
     * AJAX: Test Connector connection
     */
    public function ajax_test_connector() {
        check_ajax_referer('mwm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'magento-wordpress-migrator')));
        }

        $connector_url = sanitize_url($_POST['connector_url'] ?? '');
        $connector_api_key = sanitize_text_field($_POST['connector_api_key'] ?? '');

        // Debug logging - always on for connector testing
        error_log('MWM: ==================================================');
        error_log('MWM: CONNECTOR TEST START');
        error_log('MWM: Connector URL: ' . $connector_url);
        error_log('MWM: API Key length: ' . strlen($connector_api_key));

        // Validate required fields
        if (empty($connector_url) || empty($connector_api_key)) {
            $error_msg = __('Missing required connector credentials', 'magento-wordpress-migrator');
            error_log('MWM: ❌ Missing credentials');
            wp_send_json_error(array('message' => $error_msg));
        }

        require_once MWM_PLUGIN_DIR . 'includes/class-mwm-connector-client.php';

        // Step 1: Test ping (no auth required)
        error_log('MWM: Step 1: Testing ping endpoint...');
        try {
            $connector = new MWM_Connector_Client($connector_url, $connector_api_key);
            $ping_result = $connector->ping();

            if ($ping_result['success']) {
                error_log('MWM: ✅ Ping successful: ' . $ping_result['message']);
            } else {
                error_log('MWM: ❌ Ping failed: ' . $ping_result['message']);
                wp_send_json_error(array(
                    'message' => 'Ping failed: ' . $ping_result['message'],
                    'step' => 'ping',
                    'error_details' => $ping_result
                ));
            }
        } catch (Exception $e) {
            error_log('MWM: ❌ Ping exception: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Ping failed with exception: ' . $e->getMessage(),
                'step' => 'ping'
            ));
        }

        // Step 2: Test debug endpoint (auth required, no Magento)
        error_log('MWM: Step 2: Testing debug endpoint...');
        try {
            $debug_result = $connector->test_debug();

            if (is_wp_error($debug_result)) {
                error_log('MWM: ❌ Debug endpoint failed: ' . $debug_result->get_error_message());
                wp_send_json_error(array(
                    'message' => 'Authentication failed or debug endpoint error: ' . $debug_result->get_error_message(),
                    'step' => 'debug_auth',
                    'error_details' => $debug_result->get_error_message()
                ));
            }

            error_log('MWM: ✅ Debug endpoint successful');
            error_log('MWM: Debug info: ' . print_r($debug_result, true));
        } catch (Exception $e) {
            error_log('MWM: ❌ Debug endpoint exception: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Debug endpoint failed: ' . $e->getMessage(),
                'step' => 'debug'
            ));
        }

        // Step 3: Test full connection with Magento
        error_log('MWM: Step 3: Testing full connection with Magento...');
        try {
            $result = $connector->test_connection();

            if ($result['success']) {
                error_log('MWM: ✅ Full connection successful');
                $message = sprintf(
                    __('Connection successful! Magento version: %s', 'magento-wordpress-migrator'),
                    $result['magento_version'] ?? 'Unknown'
                );

                // Combine debug info
                $full_debug_info = array_merge(
                    $debug_result,
                    array(
                        'magento_version' => $result['magento_version'],
                        'connection_test' => $result
                    )
                );

                error_log('MWM: ✅ ALL TESTS PASSED');
                error_log('MWM: CONNECTOR TEST END');
                error_log('MWM: ==================================================');

                wp_send_json_success(array(
                    'message' => $message,
                    'magento_version' => $result['magento_version'] ?? 'Unknown',
                    'debug_info' => $full_debug_info
                ));
            } else {
                error_log('MWM: ❌ Full connection failed: ' . $result['message']);
                wp_send_json_error(array(
                    'message' => $result['message'],
                    'step' => 'magento_connection',
                    'debug_info' => $debug_result
                ));
            }

        } catch (Exception $e) {
            error_log('MWM: ❌ Full connection exception: ' . $e->getMessage());
            error_log('MWM: Exception trace: ' . $e->getTraceAsString());
            error_log('MWM: CONNECTOR TEST END');
            error_log('MWM: ==================================================');

            wp_send_json_error(array(
                'message' => __('Connection failed: ', 'magento-wordpress-migrator') . $e->getMessage(),
                'step' => 'magento_exception',
                'debug_info' => $debug_result,
                'exception' => $e->getMessage()
            ));
        }
    }

    /**
     * AJAX: Start migration
     */
    public function ajax_start_migration() {
        // Start output buffering to prevent PHP errors from breaking JSON response
        ob_start();

        // Set error handler to catch any PHP errors
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            error_log("MWM PHP Error: [$errno] $errstr in $errfile on line $errline");
            return true; // Prevent default error output
        });

        error_log('MWM: ============================================');
        error_log('MWM: ajax_start_migration CALLED');

        // Check nonce
        if (!check_ajax_referer('mwm_ajax_nonce', 'nonce', false)) {
            error_log('MWM: ❌ Invalid nonce');
            ob_end_clean();
            wp_send_json_error(array('message' => __('Invalid security token', 'magento-wordpress-migrator')));
        }
        error_log('MWM: ✓ Nonce verified');

        if (!current_user_can('manage_options')) {
            error_log('MWM: ❌ Permission denied');
            ob_end_clean();
            restore_error_handler();
            wp_send_json_error(array('message' => __('Permission denied', 'magento-wordpress-migrator')));
        }
        error_log('MWM: ✓ User has permission');

        $migration_type = sanitize_text_field($_POST['migration_type'] ?? '');
        error_log('MWM: Migration type: ' . $migration_type);

        // Check page parameter for products (new format: 'all' or page number like '1', '2', etc.)
        $page_param = isset($_POST['page']) ? sanitize_text_field($_POST['page']) : 'all';

        // Convert to old format for backward compatibility
        $specific_page = null;
        $migrate_all = true; // Default to migrate all

        if ($page_param !== 'all') {
            $specific_page = intval($page_param);
            $migrate_all = false;
            error_log('MWM: Migrating specific page: ' . $specific_page);
        } else {
            error_log('MWM: Migrating all pages');
        }

        if (!in_array($migration_type, array('products', 'categories', 'customers', 'orders'))) {
            error_log('MWM: ❌ Invalid migration type: ' . $migration_type);
            ob_end_clean();
            restore_error_handler();
            wp_send_json_error(array('message' => __('Invalid migration type', 'magento-wordpress-migrator')));
        }

        // Get stored credentials
        $settings = get_option('mwm_settings', array());
        error_log('MWM: Settings keys: ' . print_r(array_keys($settings), true));

        // Check connector credentials
        $has_connector_creds = !empty($settings['connector_url']) && !empty($settings['connector_api_key']);

        error_log('MWM: Has Connector creds: ' . ($has_connector_creds ? 'YES' : 'NO'));

        // Validate connector credentials
        if (!$has_connector_creds) {
            error_log('MWM: ❌ No connector credentials configured');
            ob_end_clean();
            restore_error_handler();
            wp_send_json_error(array('message' => __('Please configure connector credentials first', 'magento-wordpress-migrator')));
        }

        // Verify connector connection works
        error_log('MWM: Verifying connector connection before scheduling migration...');
        try {
            error_log('MWM: Testing connector connection...');
            require_once MWM_PLUGIN_DIR . 'includes/class-mwm-connector-client.php';
            $connector_client = new MWM_Connector_Client(
                $settings['connector_url'],
                $settings['connector_api_key']
            );
            $result = $connector_client->test_connection();
            if ($result['success']) {
                error_log('MWM: ✓ Connector connection verified');
            } else {
                error_log('MWM: ❌ Connector connection failed: ' . $result['message']);
                ob_end_clean();
                restore_error_handler();
                wp_send_json_error(array(
                    'message' => __('Cannot start migration: Unable to connect to Magento.', 'magento-wordpress-migrator') . ' ' . $result['message']
                ));
            }
        } catch (Exception $e) {
            error_log('MWM: ❌ Connector connection error: ' . $e->getMessage());
            ob_end_clean();
            restore_error_handler();
            wp_send_json_error(array(
                'message' => __('Cannot start migration: Unable to connect to Magento.', 'magento-wordpress-migrator') . ' ' . $e->getMessage()
            ));
        }

        error_log('MWM: ✓ Connection verified, proceeding with migration');

        // Initialize migration
        $migration_id = uniqid('mwm_migration_');
        error_log('MWM: Generated migration ID: ' . $migration_id);

        $migration_data = array(
            'id' => $migration_id,
            'type' => $migration_type,
            'status' => 'processing',
            'started' => current_time('mysql'),
            'total' => 0,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => array(),
            'current_item' => 'Initializing...',
            'specific_page' => $specific_page,
            'migrate_all' => $migrate_all
        );

        $saved = update_option('mwm_current_migration', $migration_data);
        error_log('MWM: Migration data saved to DB: ' . ($saved ? 'YES' : 'NO'));

        // Set longer timeout just in case for this initialization
        set_time_limit(300);

        // Schedule migration in background
        error_log('MWM: Scheduling migration in background for ID: ' . $migration_id);
        
        // Use wp_schedule_single_event to trigger the migration in the background
        wp_schedule_single_event(time(), 'mwm_process_migration', array($migration_id));
        
        // Manually trigger cron spawning to ensure it starts as soon as possible
        spawn_cron();

        ob_end_clean();
        restore_error_handler();

        wp_send_json_success(array(
            'message' => __('Migration started in background', 'magento-wordpress-migrator'),
            'migration_id' => $migration_id
        ));
    }


    /**
     * AJAX: Get migration progress
     */
    public function ajax_get_progress() {
        check_ajax_referer('mwm_ajax_nonce', 'nonce');

        $migration_data = get_option('mwm_current_migration', array());

        if (empty($migration_data)) {
            wp_send_json_error(array('message' => __('No migration in progress', 'magento-wordpress-migrator')));
        }

        wp_send_json_success($migration_data);
    }

    /**
     * AJAX: Cancel migration
     */
    public function ajax_cancel_migration() {
        check_ajax_referer('mwm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'magento-wordpress-migrator')));
        }

        $migration_data = get_option('mwm_current_migration', array());

        if (!empty($migration_data)) {
            $migration_data['status'] = 'cancelled';
            update_option('mwm_current_migration', $migration_data);
        }

        wp_send_json_success(array('message' => __('Migration cancelled', 'magento-wordpress-migrator')));
    }

    /**
     * AJAX: Get statistics
     */
    public function ajax_get_stats() {
        check_ajax_referer('mwm_ajax_nonce', 'nonce');

        $stats = array(
            'products' => array('migrated' => 0, 'total' => 0),
            'categories' => array('migrated' => 0, 'total' => 0),
            'customers' => array('migrated' => 0, 'total' => 0),
            'orders' => array('migrated' => 0, 'total' => 0)
        );

        // Get product stats
        $products = wp_count_posts('product');
        $stats['products']['migrated'] = $products->publish + $products->draft;

        // Get category stats
        $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        $stats['categories']['migrated'] = is_array($categories) ? count($categories) : 0;

        // Get customer stats
        $customers = count_users();
        $stats['customers']['migrated'] = $customers['total_users'];

        // Get order stats
        $orders = wp_count_posts('shop_order');
        $stats['orders']['migrated'] = $orders->wc_completed + $orders->wc_processing + $orders->wc_pending;

        // Get totals from Magento via connector
        $settings = get_option('mwm_settings', array());
        if (!empty($settings['connector_url']) && !empty($settings['connector_api_key'])) {
            try {
                require_once MWM_PLUGIN_DIR . 'includes/class-mwm-connector-client.php';
                $connector = new MWM_Connector_Client(
                    $settings['connector_url'],
                    $settings['connector_api_key']
                );

                $stats['products']['total'] = $connector->get_products_count();
                $stats['categories']['total'] = $connector->get_categories_count();
                $stats['customers']['total'] = $connector->get_customers_count();
                $stats['orders']['total'] = $connector->get_orders_count();
            } catch (Exception $e) {
                // Connection failed, keep totals as 0
            }
        }

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Get product pages count
     */
    public function ajax_get_product_pages() {
        check_ajax_referer('mwm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'magento-wordpress-migrator')));
        }

        $settings = get_option('mwm_settings', array());
        $batch_size = 20; // Default batch size

        try {
            require_once MWM_PLUGIN_DIR . 'includes/class-mwm-connector-client.php';
            $connector = new MWM_Connector_Client(
                $settings['connector_url'],
                $settings['connector_api_key']
            );

            $total_products = $connector->get_products_count();
            $total_products = is_wp_error($total_products) ? 0 : max(1, intval($total_products));
            $total_pages = max(1, ceil($total_products / $batch_size));

            wp_send_json_success(array(
                'total_products' => $total_products,
                'total_pages' => $total_pages,
                'batch_size' => $batch_size
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Display PHP version notice
     */
    public function php_version_notice() {
        printf(
            '<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
            __('Magento to WordPress Migrator:', 'magento-wordpress-migrator'),
            sprintf(__('PHP version %1$s or higher is required. You are running version %2$s.', 'magento-wordpress-migrator'), MWM_MIN_PHP_VERSION, PHP_VERSION)
        );
    }

    /**
     * Display WordPress version notice
     */
    public function wp_version_notice() {
        printf(
            '<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
            __('Magento to WordPress Migrator:', 'magento-wordpress-migrator'),
            sprintf(__('WordPress version %1$s or higher is required. You are running version %2$s.', 'magento-wordpress-migrator'), MWM_MIN_WP_VERSION, get_bloginfo('version'))
        );
    }

    /**
     * Display WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        // Double check in case it was loaded after our constructor
        if (class_exists('WooCommerce')) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
            __('Magento to WordPress Migrator:', 'magento-wordpress-migrator'),
            __('WooCommerce is not installed or active. This plugin requires WooCommerce to function.', 'magento-wordpress-migrator')
        );
    }

}

/**
 * Process migration in background
 */
function mwm_process_migration_callback($migration_id) {
    // Increase memory and time limits for background processing
    @set_time_limit(0); 
    @ini_set('memory_limit', '512M');

    // Include required files - CRITICAL for AJAX context
    require_once MWM_PLUGIN_DIR . 'includes/class-mwm-logger.php';
    require_once MWM_PLUGIN_DIR . 'includes/class-mwm-migrator-base.php';
    error_log('MWM: ==================================================');
    error_log('MWM: mwm_process_migration_callback CALLED with ID: ' . $migration_id);

    $migration_data = get_option('mwm_current_migration', array());
    error_log('MWM: Retrieved migration data: ' . print_r($migration_data, true));

    if (empty($migration_data) || $migration_data['id'] !== $migration_id) {
        error_log('MWM: ❌ Migration data not found or ID mismatch');
        return;
    }

    // Check if cancelled
    if ($migration_data['status'] === 'cancelled') {
        error_log('MWM: Migration was cancelled, exiting');
        return;
    }

    $settings = get_option('mwm_settings', array());
    error_log('MWM: Retrieved settings for migration');

    try {
        // Create connector client
        error_log('MWM: Creating connector client');
        if (empty($settings['connector_url']) || empty($settings['connector_api_key'])) {
            throw new Exception(__('Connector credentials not configured', 'magento-wordpress-migrator'));
        }
        require_once MWM_PLUGIN_DIR . 'includes/class-mwm-connector-client.php';
        $connector_client = new MWM_Connector_Client(
            $settings['connector_url'],
            $settings['connector_api_key']
        );
        error_log('MWM: Connector client created successfully');

        // Initialize appropriate migrator
        error_log('MWM: Initializing ' . $migration_data['type'] . ' migrator');

        // Include migrator base class
        require_once MWM_PLUGIN_DIR . 'includes/class-mwm-migrator-base.php';

        $migrator = null;
        switch ($migration_data['type']) {
            case 'products':
                require_once MWM_PLUGIN_DIR . 'includes/class-mwm-migrator-products.php';
                $migrator = new MWM_Migrator_Products($connector_client);
                break;
            case 'categories':
                require_once MWM_PLUGIN_DIR . 'includes/class-mwm-migrator-categories.php';
                $migrator = new MWM_Migrator_Categories($connector_client);
                break;
            case 'customers':
                require_once MWM_PLUGIN_DIR . 'includes/class-mwm-migrator-customers.php';
                $migrator = new MWM_Migrator_Customers($connector_client);
                break;
            case 'orders':
                require_once MWM_PLUGIN_DIR . 'includes/class-mwm-migrator-orders.php';
                $migrator = new MWM_Migrator_Orders($connector_client);
                break;
            default:
                throw new Exception('Invalid migration type');
        }

        if (!$migrator) {
            throw new Exception('Failed to create migrator instance');
        }

        error_log('MWM: Migrator created successfully, starting run() method');

        // Run migration
        $stats = $migrator->run();
        error_log('MWM: ✓ Migration completed. Stats: ' . print_r($stats, true));

        // Update migration data
        $migration_data['status'] = 'completed';
        $migration_data['total'] = $stats['total'];
        $migration_data['processed'] = $stats['processed'];
        $migration_data['successful'] = $stats['successful'];
        $migration_data['failed'] = $stats['failed'];
        $migration_data['completed'] = current_time('mysql');

        update_option('mwm_current_migration', $migration_data);
        error_log('MWM: ✓ Migration status updated to completed');

    } catch (Exception $e) {
        error_log('MWM: ❌ MIGRATION EXCEPTION: ' . $e->getMessage());
        error_log('MWM: Exception trace: ' . $e->getTraceAsString());

        $migration_data['status'] = 'failed';
        $migration_data['errors'][] = array(
            'item' => 'migration',
            'message' => $e->getMessage(),
            'time' => current_time('mysql')
        );
        update_option('mwm_current_migration', $migration_data);

        MWM_Logger::log('error', 'migration_failed', '', $e->getMessage());
    }
}

// Register the callback
add_action('mwm_process_migration', 'mwm_process_migration_callback');

// Initialize the plugin
function magento_wordpress_migrator() {
    return Magento_WordPress_Migrator::get_instance();
}

// Start the plugin
magento_wordpress_migrator();

/**
 * Declare WooCommerce feature compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        // Declare HPOS (High-Performance Order Storage) compatibility
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);

        // Declare compatibility with other WC features
        // The plugin doesn't use any incompatible features, so we can declare compatibility
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('analytics', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_and_checkout_blocks', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('product_blocks', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('min_max_quantity', __FILE__, true);
    }
});

/**
 * Plugin activation
 */
register_activation_hook(__FILE__, 'mwm_activate_plugin');

function mwm_activate_plugin() {
    // Create log table
    global $wpdb;
    $table_name = $wpdb->prefix . 'mwm_migration_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        migration_type varchar(50) NOT NULL,
        item_id varchar(255) DEFAULT NULL,
        item_type varchar(50) DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        message text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY migration_type (migration_type),
        KEY status (status),
        KEY item_id (item_id(100))
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Set default options
    add_option('mwm_settings', array(
        'db_host' => 'localhost',
        'db_port' => 3306,
        'table_prefix' => ''
    ));
    add_option('mwm_migration_stats', array());

    // Schedule cleanup event
    if (!wp_next_scheduled('mwm_cleanup_logs')) {
        wp_schedule_event(time(), 'daily', 'mwm_cleanup_logs');
    }
}

/**
 * Plugin deactivation
 */
register_deactivation_hook(__FILE__, 'mwm_deactivate_plugin');

function mwm_deactivate_plugin() {
    // Clear scheduled hooks
    wp_clear_scheduled_hook('mwm_process_migration');
    wp_clear_scheduled_hook('mwm_cleanup_logs');
}

/**
 * Cleanup old logs (runs daily)
 */
add_action('mwm_cleanup_logs', 'mwm_cleanup_old_logs');

function mwm_cleanup_old_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mwm_migration_logs';

    // Delete logs older than 30 days
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            30
        )
    );
}
