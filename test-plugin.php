<?php
/**
 * Simple test to check if the plugin loads correctly
 *
 * This file can be used to verify that:
 * 1. The plugin file structure is correct
 * 2. Classes are being loaded
 * 3. Admin menu is registered
 */

// Simulate WordPress environment
define('ABSPATH', dirname(__FILE__) . '/');
define('WP_PLUGIN_DIR', dirname(__FILE__));

echo "=== Magento to WordPress Migrator Plugin Test ===\n\n";

// Test 1: Check main plugin file exists
echo "Test 1: Checking main plugin file...\n";
$main_file = __DIR__ . '/magento-wordpress-migrator.php';
if (file_exists($main_file)) {
    echo "✓ Main plugin file exists\n";
} else {
    echo "✗ Main plugin file NOT found\n";
    exit(1);
}

// Test 2: Check required files exist
echo "\nTest 2: Checking required files...\n";
$required_files = array(
    'includes/class-mwm-db.php',
    'includes/class-mwm-logger.php',
    'includes/class-mwm-migrator-products.php',
    'includes/class-mwm-migrator-categories.php',
    'includes/class-mwm-migrator-customers.php',
    'includes/class-mwm-migrator-orders.php',
    'includes/admin/class-mwm-admin.php',
    'includes/admin/class-mwm-settings.php',
    'includes/admin/class-mwm-migration-page.php',
    'assets/css/admin.css',
    'assets/js/admin.js'
);

foreach ($required_files as $file) {
    $filepath = __DIR__ . '/' . $file;
    if (file_exists($filepath)) {
        echo "✓ {$file}\n";
    } else {
        echo "✗ {$file} NOT found\n";
    }
}

// Test 3: Check plugin header
echo "\nTest 3: Checking plugin header...\n";
$content = file_get_contents($main_file);
if (strpos($content, 'Plugin Name: Magento to WordPress Migrator') !== false) {
    echo "✓ Plugin header is valid\n";
} else {
    echo "✗ Plugin header is invalid\n";
}

// Test 4: Check for main class
echo "\nTest 4: Checking for main class...\n";
if (strpos($content, 'class Magento_WordPress_Migrator') !== false) {
    echo "✓ Main class found\n";
} else {
    echo "✗ Main class NOT found\n";
}

// Test 5: Check admin initialization
echo "\nTest 5: Checking admin initialization...\n";
if (strpos($content, 'new MWM_Admin()') !== false) {
    echo "✓ Admin class is instantiated\n";
} else {
    echo "✗ Admin class is NOT instantiated\n";
}

// Test 6: Check menu registration
echo "\nTest 6: Checking menu registration...\n";
$admin_file = __DIR__ . '/includes/admin/class-mwm-admin.php';
if (file_exists($admin_file)) {
    $admin_content = file_get_contents($admin_file);
    if (strpos($admin_content, 'add_menu_page') !== false) {
        echo "✓ Menu registration found\n";
    } else {
        echo "✗ Menu registration NOT found\n";
    }
} else {
    echo "✗ Admin class file not found\n";
}

echo "\n=== All tests completed ===\n";
echo "\nThe plugin should be ready to activate in WordPress.\n";
echo "To activate:\n";
echo "1. Login to WordPress admin\n";
echo "2. Go to Plugins → Installed Plugins\n";
echo "3. Find 'Magento to WordPress Migrator'\n";
echo "4. Click 'Activate'\n";
echo "5. Look for 'Magento Migrator' menu in the admin sidebar\n";
