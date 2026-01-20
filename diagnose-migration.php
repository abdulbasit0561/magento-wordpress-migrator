<?php
/**
 * Migration Startup Diagnostic Script
 * Run this to diagnose migration startup issues
 */

// Load WordPress
require_once(dirname(__FILE__) . '/../../../wp-load.php');

echo "=== Magento Migration Startup Diagnostic ===\n\n";

// 1. Check plugin is active
echo "1. Checking plugin status...\n";
$active_plugins = get_option('active_plugins', array());
$plugin_active = in_array('magento-wordpress-migrator/magento-wordpress-migrator.php', $active_plugins);
echo "   Plugin active: " . ($plugin_active ? "YES ✓" : "NO ✗") . "\n\n";

if (!$plugin_active) {
    die("ERROR: Plugin is not active!\n");
}

// 2. Check settings
echo "2. Checking settings...\n";
$settings = get_option('mwm_settings', array());
echo "   Settings found: " . (empty($settings) ? "NO ✗" : "YES ✓") . "\n";

$has_db = !empty($settings['db_host']) && !empty($settings['db_name']) && !empty($settings['db_user']);
$has_api = !empty($settings['store_url']) &&
           !empty($settings['consumer_key']) &&
           !empty($settings['consumer_secret']) &&
           !empty($settings['access_token']) &&
           !empty($settings['access_token_secret']);

echo "   Database credentials: " . ($has_db ? "YES ✓" : "NO") . "\n";
echo "   API credentials: " . ($has_api ? "YES ✓" : "NO") . "\n";

if (!$has_db && !$has_api) {
    die("\nERROR: No credentials configured!\n");
}

echo "   Connection type: " . ($has_api ? "API" : "Database") . "\n\n";

// 3. Check if migration is already in progress
echo "3. Checking for existing migration...\n";
$current_migration = get_option('mwm_current_migration', array());
if (!empty($current_migration)) {
    echo "   Existing migration found:\n";
    echo "   - ID: " . ($current_migration['id'] ?? 'N/A') . "\n";
    echo "   - Type: " . ($current_migration['type'] ?? 'N/A') . "\n";
    echo "   - Status: " . ($current_migration['status'] ?? 'N/A') . "\n";
    echo "   - Started: " . ($current_migration['started'] ?? 'N/A') . "\n";
    echo "   - Progress: " . ($current_migration['processed'] ?? 0) . "/" . ($current_migration['total'] ?? 0) . "\n\n";

    if ($current_migration['status'] === 'processing') {
        echo "   WARNING: Migration already in progress!\n";
        echo "   This may prevent new migrations from starting.\n";
        echo "   To clear: delete_option('mwm_current_migration');\n\n";
    }
} else {
    echo "   No existing migration ✓\n\n";
}

// 4. Test connection
echo "4. Testing connection...\n";
if ($has_api) {
    echo "   Testing API connection...\n";
    try {
        $connector = new MWM_API_Connector(
            $settings['store_url'],
            $settings['api_version'] ?? 'V1',
            $settings['consumer_key'],
            $settings['consumer_secret'],
            $settings['access_token'],
            $settings['access_token_secret']
        );

        $result = $connector->test_connection();
        if ($result['success']) {
            echo "   API connection: SUCCESS ✓\n";
        } else {
            echo "   API connection: FAILED ✗\n";
            echo "   Error: " . $result['message'] . "\n";
        }
    } catch (Exception $e) {
        echo "   API connection: ERROR ✗\n";
        echo "   Error: " . $e->getMessage() . "\n";
    }
} elseif ($has_db) {
    echo "   Testing database connection...\n";
    try {
        $db = new MWM_DB(
            $settings['db_host'],
            $settings['db_name'],
            $settings['db_user'],
            $settings['db_password'],
            $settings['db_port'] ?? 3306,
            $settings['table_prefix'] ?? ''
        );
        echo "   Database connection: SUCCESS ✓\n";
    } catch (Exception $e) {
        echo "   Database connection: FAILED ✗\n";
        echo "   Error: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// 5. Check WP-Cron status
echo "5. Checking WP-Cron...\n";
$cron_test = wp_schedule_single_event(time() + 1, 'mwm_test_cron_hook');
if ($cron_test) {
    echo "   WP-Cron scheduling: WORKING ✓\n";
    wp_clear_scheduled_hook('mwm_test_cron_hook');
} else {
    echo "   WP-Cron scheduling: MAY HAVE ISSUES ✗\n";
    echo "   Check DISABLE_WP_CRON in wp-config.php\n";
}
echo "\n";

// 6. Check AJAX endpoint
echo "6. Checking AJAX setup...\n";
echo "   AJAX URL: " . admin_url('admin-ajax.php') . "\n";
echo "   Nonce: " . (wp_create_nonce('mwm_ajax_nonce') ? "Can be created ✓" : "CANNOT BE CREATED ✗") . "\n\n";

// 7. Test migration initialization (dry run)
echo "7. Testing migration initialization (dry run)...\n";
try {
    $migration_id = uniqid('mwm_migration_');
    echo "   Migration ID: $migration_id\n";

    $migration_data = array(
        'id' => $migration_id,
        'type' => 'products',
        'status' => 'processing',
        'started' => current_time('mysql'),
        'total' => 0,
        'processed' => 0,
        'successful' => 0,
        'failed' => 0,
        'errors' => array(),
        'current_item' => 'Testing...'
    );

    $saved = update_option('mwm_current_migration', $migration_data);
    echo "   Migration data saved: " . ($saved ? "YES ✓" : "NO ✗") . "\n";

    $retrieved = get_option('mwm_current_migration');
    echo "   Migration data retrieved: " . (!empty($retrieved) ? "YES ✓" : "NO ✗") . "\n";

    // Clean up test data
    delete_option('mwm_current_migration');
    echo "   Test data cleaned up ✓\n\n";

} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// 8. Check WooCommerce
echo "8. Checking WooCommerce...\n";
$woocommerce_active = class_exists('WooCommerce');
echo "   WooCommerce active: " . ($woocommerce_active ? "YES ✓" : "NO ✗") . "\n";
if ($woocommerce_active) {
    echo "   WooCommerce version: " . WC()->version . "\n";
}
echo "\n";

echo "=== Diagnostic Complete ===\n\n";

echo "RECOMMENDATIONS:\n";
if (!$has_db && !$has_api) {
    echo "- Configure either database or API credentials in plugin settings\n";
}
if (!$woocommerce_active) {
    echo "- Install and activate WooCommerce plugin\n";
}
if (!empty($current_migration) && $current_migration['status'] === 'processing') {
    echo "- Clear stuck migration: Run this in WordPress admin or PHP:\n";
    echo "  delete_option('mwm_current_migration');\n";
}
echo "- Check browser console for JavaScript errors when clicking 'Migrate'\n";
echo "- Check WordPress debug log: wp-content/debug.log\n";
echo "- Enable WP_DEBUG in wp-config.php for detailed logging\n\n";

echo "If migration still doesn't start:\n";
echo "1. Clear the stuck migration: delete_option('mwm_current_migration');\n";
echo "2. Refresh the migration page\n";
echo "3. Try clicking 'Migrate Products' again\n";
