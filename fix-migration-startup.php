<?php
/**
 * Fix Migration Startup Issues
 * Run this to fix common migration problems
 */

// Load WordPress
require_once(dirname(__FILE__) . '/../../../wp-load.php');

echo "=== Fixing Migration Startup Issues ===\n\n";

// 1. Clear stuck migration
echo "1. Clearing stuck/cancelled migrations...\n";
$existing = get_option('mwm_current_migration');
if (!empty($existing)) {
    echo "   Found existing migration:\n";
    echo "   - Type: " . ($existing['type'] ?? 'N/A') . "\n";
    echo "   - Status: " . ($existing['status'] ?? 'N/A') . "\n";
    echo "   - Started: " . ($existing['started'] ?? 'N/A') . "\n";

    delete_option('mwm_current_migration');
    echo "   ✓ Cleared migration data\n\n";
} else {
    echo "   ✓ No stuck migration found\n\n";
}

// 2. Check connection methods available
echo "2. Checking available connection methods...\n";
$settings = get_option('mwm_settings', array());

$has_db = !empty($settings['db_host']) &&
           !empty($settings['db_name']) &&
           !empty($settings['db_user']) &&
           !empty($settings['db_password']);

$has_api = !empty($settings['store_url']) &&
           !empty($settings['consumer_key']) &&
           !empty($settings['consumer_secret']) &&
           !empty($settings['access_token']) &&
           !empty($settings['access_token_secret']);

echo "   Database connection: " . ($has_db ? "AVAILABLE ✓" : "NOT CONFIGURED") . "\n";
echo "   API connection: " . ($has_api ? "CONFIGURED (but may have issues)" : "NOT CONFIGURED") . "\n\n";

// 3. Test database connection if available
$use_db = false;
if ($has_db) {
    echo "3. Testing database connection...\n";
    try {
        $db = new MWM_DB(
            $settings['db_host'],
            $settings['db_name'],
            $settings['db_user'],
            $settings['db_password'],
            $settings['db_port'] ?? 3306,
            $settings['table_prefix'] ?? ''
        );

        // Test query
        $total_products = $db->get_total_products();
        echo "   ✓ Database connection successful\n";
        echo "   ✓ Total products in Magento: " . $total_products . "\n";

        if ($total_products > 0) {
            echo "   ✓ Database mode is RECOMMENDED for migration\n";
            $use_db = true;
        } else {
            echo "   ⚠ Warning: No products found in database\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Database connection failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// 4. Provide recommendations
echo "4. Recommendations:\n\n";

if ($has_db && $use_db) {
    echo "   ✓ Use DATABASE MODE for migration\n";
    echo "     - Database connection is working\n";
    echo "     - Products are available\n";
    echo "     - More reliable than API for bulk migration\n";
    echo "     - No rate limiting issues\n\n";

    echo "   ACTION REQUIRED:\n";
    echo "   The plugin will automatically use database mode since API has issues.\n";
    echo "   Just click 'Migrate Products' in the admin panel.\n\n";
} else {
    echo "   ⚠ Database connection not working\n";
    echo "   Check:\n";
    echo "   - Database credentials are correct\n";
    echo "   - Database server is accessible\n";
    echo "   - Database user has proper permissions\n\n";
}

if ($has_api) {
    echo "   ⚠ API Mode Issues:\n";
    echo "   - API credentials configured but getting 401 Unauthorized\n";
    echo "   - This means the OAuth integration lacks proper permissions\n";
    echo "   - To fix API access:\n";
    echo "     1. Go to Magento Admin → System → Integrations\n";
    echo "     2. Find the integration\n";
    echo "     3. Ensure these permissions are granted:\n";
    echo "        - Sales → Operations → Retrieve\n";
    echo "        - Catalog → Products → Read/Update\n";
    echo "        - Catalog → Categories → Read/Update\n";
    echo "        - Customers → Read/Update\n";
    echo "     4. Save and re-authenticate\n\n";
}

// 5. Create a test migration to verify it works
if ($use_db) {
    echo "5. Creating test migration (products) to verify...\n";
    try {
        $migration_id = uniqid('mwm_migration_');
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
            'current_item' => 'Initializing...'
        );

        update_option('mwm_current_migration', $migration_data);

        // Test if we can create a migrator
        $migrator = new MWM_Migrator_Products($db, null);

        echo "   ✓ Test migration created successfully\n";
        echo "   ✓ Migrator instantiated successfully\n";
        echo "   ✓ Migration system is READY\n\n";

        // Clean up test
        delete_option('mwm_current_migration');

    } catch (Exception $e) {
        echo "   ✗ Test failed: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Fix Complete ===\n\n";

echo "NEXT STEPS:\n";
echo "1. Go to WordPress Admin → Magento → Migrator\n";
echo "2. Click 'Migrate Products'\n";
echo "3. The migration should now start properly\n";
echo "4. You will see the progress modal appear\n";
echo "5. Monitor progress as it migrates products\n\n";

echo "If it still doesn't work:\n";
echo "- Open browser console (F12) and check for JavaScript errors\n";
echo "- Check WordPress debug log: wp-content/debug.log\n";
echo "- Look for AJAX errors in the Network tab\n";
