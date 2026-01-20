<?php
/**
 * Test Migration Startup with Current Settings
 * This simulates what happens when user clicks "Migrate Products"
 */

// Load WordPress
require_once(dirname(__FILE__) . '/../../../wp-load.php');

echo "=== Testing Migration Startup ===\n\n";

// 1. Check settings
echo "1. Checking settings...\n";
$settings = get_option('mwm_settings', array());

$has_db = !empty($settings['db_host']) &&
           !empty($settings['db_name']) &&
           !empty($settings['db_user']);

$has_api = !empty($settings['store_url']) &&
           !empty($settings['consumer_key']) &&
           !empty($settings['consumer_secret']) &&
           !empty($settings['access_token']) &&
           !empty($settings['access_token_secret']);

echo "   Database credentials: " . ($has_db ? "Configured ✓" : "Not configured") . "\n";
echo "   API credentials: " . ($has_api ? "Configured ✓" : "Not configured") . "\n\n";

// 2. Test connections (exactly as AJAX handler does)
echo "2. Testing connections (as AJAX handler would)...\n";
$connection_ok = false;
$connection_errors = array();
$connection_method = '';

// Test API
if ($has_api) {
    echo "   Testing API connection...\n";
    try {
        require_once MWM_PLUGIN_DIR . 'includes/class-mwm-api-connector.php';
        $api_connector = new MWM_API_Connector(
            $settings['store_url'],
            $settings['api_version'] ?? 'V1',
            $settings['consumer_key'],
            $settings['consumer_secret'],
            $settings['access_token'],
            $settings['access_token_secret']
        );
        $result = $api_connector->test_connection();
        if ($result['success']) {
            $connection_ok = true;
            $connection_method = 'API';
            echo "   ✓ API connection WORKS\n";
        } else {
            $connection_errors['api'] = $result['message'];
            echo "   ✗ API connection failed: " . $result['message'] . "\n";
        }
    } catch (Exception $e) {
        $connection_errors['api'] = $e->getMessage();
        echo "   ✗ API connection error: " . $e->getMessage() . "\n";
    }
}

// Test Database
if ($has_db && !$connection_ok) {
    echo "   Testing Database connection...\n";
    try {
        require_once MWM_PLUGIN_DIR . 'includes/class-mwm-db.php';
        $db_connector = new MWM_DB(
            $settings['db_host'],
            $settings['db_name'],
            $settings['db_user'],
            $settings['db_password'] ?? '',
            $settings['db_port'] ?? 3306,
            $settings['table_prefix'] ?? ''
        );
        $test_result = $db_connector->get_var("SELECT 1");
        if ($test_result == '1') {
            $connection_ok = true;
            $connection_method = 'Database';
            echo "   ✓ Database connection WORKS\n";
        } else {
            $connection_errors['db'] = 'Database query failed';
            echo "   ✗ Database query failed\n";
        }
    } catch (Exception $e) {
        $connection_errors['db'] = $e->getMessage();
        echo "   ✗ Database connection error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// 3. Show result
if ($connection_ok) {
    echo "=== SUCCESS ===\n";
    echo "✓ Connection verified via $connection_method\n";
    echo "✓ Migration can START successfully\n";
    echo "\nWhat will happen when user clicks 'Migrate Products':\n";
    echo "1. Connection test runs (passes)\n";
    echo "2. Migration scheduled in background\n";
    echo "3. Progress modal appears immediately\n";
    echo "4. Real-time progress shows (0% → 100%)\n";
} else {
    echo "=== FAILURE ===\n";
    echo "✗ Cannot start migration\n";
    echo "\nUser will see this error:\n";
    echo "─────────────────────────────────────\n";
    echo "Cannot start migration: Unable to connect to Magento.\n\n";
    echo "Connection Errors:\n";
    foreach ($connection_errors as $type => $err) {
        echo "\n• " . ucfirst($type) . ': ' . $err;
    }
    echo "\n\nPlease fix the connection issue and try again.\n";
    echo "─────────────────────────────────────\n";
    echo "\n";
}

// 4. Show what needs to be fixed
echo "\n4. Required Actions:\n\n";

if (!$connection_ok) {
    if (isset($connection_errors['db'])) {
        echo "DATABASE CREDENTIALS ARE WRONG:\n";
        echo "Current: Host=" . $settings['db_host'] . ", User=" . $settings['db_user'] . "\n";
        echo "Error: " . $connection_errors['db'] . "\n\n";

        echo "TO FIX:\n";
        echo "1. Get correct database password from Magento:\n";
        echo "   cat /path/to/magento/app/etc/env.php | grep password\n";
        echo "2. Go to: WordPress Admin → Magento → Migrator → Settings\n";
        echo "3. Update the 'Database Password' field\n";
        echo "4. Click 'Save Changes'\n";
        echo "5. Click 'Test Connection'\n";
        echo "6. Try migration again\n\n";
    }

    if (isset($connection_errors['api'])) {
        echo "API CREDENTIALS AREN'T WORKING:\n";
        echo "Error: " . $connection_errors['api'] . "\n\n";

        echo "TO FIX:\n";
        echo "1. Go to Magento Admin → System → Integrations\n";
        echo "2. Ensure these permissions are granted:\n";
        echo "   - Catalog → Products → Read\n";
        echo "   - Catalog → Categories → Read\n";
        echo "3. Save and re-authenticate\n\n";

        echo "OR: Use database mode instead (simpler, more reliable)\n\n";
    }
} else {
    echo "✓ Everything is working correctly!\n";
    echo "Just click 'Migrate Products' to start.\n\n";
}

echo "=== Test Complete ===\n";
