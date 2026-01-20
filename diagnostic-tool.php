<?php
/**
 * Migration Diagnostic Tool
 * Run this to diagnose why migration is not starting
 */

// Load WordPress
require_once(dirname(__FILE__) . '/../../../wp-load.php');

echo "=== Magento to WordPress Migrator - Diagnostic Tool ===\n\n";

// 1. Check WP-Cron Status
echo "1. WP-Cron Status:\n";
echo "   WP-Cron Disabled: " . (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'YES ⚠️' : 'NO ✓') . "\n";
echo "   WP-Cron Alternative: " . (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON ? 'YES' : 'NO') . "\n";

// 2. Check Scheduled Events
echo "\n2. Scheduled Migration Events:\n";
$scheduled = wp_get_scheduled_event('mwm_process_migration');
if ($scheduled) {
    echo "   ✓ Event found!\n";
    echo "   - Scheduled for: " . date('Y-m-d H:i:s', $scheduled->timestamp) . "\n";
    echo "   - Args: " . print_r($scheduled->args, true) . "\n";
} else {
    echo "   ✗ No migration events scheduled\n";
}

// 3. Check Current Migration Data
echo "\n3. Current Migration Data:\n";
$migration_data = get_option('mwm_current_migration', array());
if (!empty($migration_data)) {
    echo "   ✓ Migration data found:\n";
    echo "   - ID: " . ($migration_data['id'] ?? 'N/A') . "\n";
    echo "   - Type: " . ($migration_data['type'] ?? 'N/A') . "\n";
    echo "   - Status: " . ($migration_data['status'] ?? 'N/A') . "\n";
    echo "   - Started: " . ($migration_data['started'] ?? 'N/A') . "\n";
    echo "   - Total: " . ($migration_data['total'] ?? 0) . "\n";
    echo "   - Processed: " . ($migration_data['processed'] ?? 0) . "\n";
    echo "   - Successful: " . ($migration_data['successful'] ?? 0) . "\n";
    echo "   - Failed: " . ($migration_data['failed'] ?? 0) . "\n";
} else {
    echo "   ℹ No migration data found\n";
}

// 4. Check Settings
echo "\n4. Plugin Settings:\n";
$settings = get_option('mwm_settings', array());
echo "   - DB Host: " . (!empty($settings['db_host']) ? '✓ Set' : '✗ Not set') . "\n";
echo "   - DB Name: " . (!empty($settings['db_name']) ? '✓ Set' : '✗ Not set') . "\n";
echo "   - DB User: " . (!empty($settings['db_user']) ? '✓ Set' : '✗ Not set') . "\n";
echo "   - Store URL: " . (!empty($settings['store_url']) ? '✓ Set' : '✗ Not set') . "\n";
echo "   - Consumer Key: " . (!empty($settings['consumer_key']) ? '✓ Set' : '✗ Not set') . "\n";
echo "   - Access Token: " . (!empty($settings['access_token']) ? '✓ Set' : '✗ Not set') . "\n";

// 5. Test Database Connection
if (!empty($settings['db_host']) && !empty($settings['db_name']) && !empty($settings['db_user'])) {
    echo "\n5. Database Connection Test:\n";
    try {
        require_once(dirname(__FILE__) . '/includes/class-mwm-db.php');
        $db = new MWM_DB(
            $settings['db_host'],
            $settings['db_name'],
            $settings['db_user'],
            $settings['db_password'] ?? '',
            $settings['db_port'] ?? 3306,
            $settings['table_prefix'] ?? ''
        );
        echo "   ✓ Database connection successful\n";

        // Test query
        $version = $db->get_magento_version();
        echo "   - Magento Version: " . ($version == 2 ? '2.x' : '1.x') . "\n";

    } catch (Exception $e) {
        echo "   ✗ Database connection failed: " . $e->getMessage() . "\n";
    }
}

// 6. Check Plugin Files
echo "\n6. Plugin File Check:\n";
$required_files = array(
    'includes/class-mwm-api-connector.php',
    'includes/class-mwm-db.php',
    'includes/class-mwm-migrator-products.php',
    'includes/class-mwm-migrator-categories.php',
    'includes/class-mwm-logger.php'
);

foreach ($required_files as $file) {
    $path = dirname(__FILE__) . '/' . $file;
    if (file_exists($path)) {
        echo "   ✓ $file\n";
    } else {
        echo "   ✗ MISSING: $file\n";
    }
}

// 7. Check WordPress Cron Jobs
echo "\n7. All WP-Cron Events:\n";
$crons = _get_cron_array();
if (empty($crons)) {
    echo "   ℹ No cron jobs scheduled\n";
} else {
    foreach ($crons as $timestamp => $cronhooks) {
        foreach ($cronhooks as $hook => $events) {
            if (strpos($hook, 'mwm') !== false) {
                echo "   - $hook at " . date('Y-m-d H:i:s', $timestamp) . "\n";
            }
        }
    }
}

// 8. Recommendations
echo "\n8. Recommendations:\n";
$mwm_cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
if ($mwm_cron_disabled) {
    echo "   ⚠️  WP-Cron is DISABLED! This prevents background migration.\n";
    echo "   Solution 1: Remove DISABLE_WP_CRON from wp-config.php\n";
    echo "   Solution 2: Set up system cron to call wp-cron.php:\n";
    echo "              */5 * * * * php /path/to/wp-cron.php > /dev/null 2>&1\n";
}

if (empty($migration_data) && !$scheduled) {
    echo "   ℹ No migration is running. Try starting a migration from the admin panel.\n";
}

if (!empty($migration_data) && $migration_data['status'] === 'processing') {
    $elapsed = time() - strtotime($migration_data['started']);
    echo "   ⚠️  Migration appears stuck (processing for " . floor($elapsed/60) . " minutes)\n";
    echo "   - Check error logs: tail -f " . WP_CONTENT_DIR . "/debug.log\n";
    echo "   - May need to increase PHP memory_limit and max_execution_time\n";
}

echo "\n=== Diagnostic Complete ===\n";
echo "\nNext Steps:\n";
echo "1. If WP-Cron is disabled, enable it or set up system cron\n";
echo "2. Try starting migration from WordPress admin\n";
echo "3. Monitor debug.log: tail -f " . WP_CONTENT_DIR . "/debug.log | grep MWM\n";
echo "4. Check for errors in the logs above\n";
