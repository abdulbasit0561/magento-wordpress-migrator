<?php
/**
 * Debug script for product migration
 * Run this script to test product migration directly
 */

// Load WordPress
require_once(dirname(__FILE__) . '/../../../wp-load.php');

// Load plugin files
require_once(dirname(__FILE__) . '/includes/class-mwm-logger.php');
require_once(dirname(__FILE__) . '/includes/class-mwm-api-connector.php');
require_once(dirname(__FILE__) . '/includes/class-mwm-db.php');
require_once(dirname(__FILE__) . '/includes/class-mwm-migrator-products.php');

echo "=== Magento to WordPress Product Migration Debug ===\n\n";

// Get settings
$settings = get_option('mwm_settings', array());

echo "Current Settings:\n";
echo "- DB Host: " . ($settings['db_host'] ?? 'not set') . "\n";
echo "- DB Name: " . ($settings['db_name'] ?? 'not set') . "\n";
echo "- Store URL: " . ($settings['store_url'] ?? 'not set') . "\n";
echo "- API Version: " . ($settings['api_version'] ?? 'not set') . "\n";
echo "- Consumer Key: " . (isset($settings['consumer_key']) ? substr($settings['consumer_key'], 0, 8) . '...' : 'not set') . "\n";
echo "- Access Token: " . (isset($settings['access_token']) ? substr($settings['access_token'], 0, 8) . '...' : 'not set') . "\n";
echo "\n";

// Determine connection type
$use_api = !empty($settings['store_url']) &&
           !empty($settings['consumer_key']) &&
           !empty($settings['consumer_secret']) &&
           !empty($settings['access_token']) &&
           !empty($settings['access_token_secret']);

echo "Connection Type: " . ($use_api ? "REST API" : "Database") . "\n\n";

try {
    $connector = null;
    $db = null;

    if ($use_api) {
        echo "Creating API Connector...\n";
        $connector = new MWM_API_Connector(
            $settings['store_url'],
            $settings['api_version'] ?? 'V1',
            $settings['consumer_key'],
            $settings['consumer_secret'],
            $settings['access_token'],
            $settings['access_token_secret']
        );
        echo "✓ API Connector created\n\n";

        // Test connection
        echo "Testing API connection...\n";
        $test_result = $connector->test_connection();
        if ($test_result['success']) {
            echo "✓ API connection successful\n\n";
        } else {
            echo "✗ API connection failed: " . $test_result['message'] . "\n\n";
            exit(1);
        }

        // Get total product count
        echo "Getting total product count...\n";
        $total_count = $connector->get_total_count('/products/search');
        echo "✓ Total products: $total_count\n\n";

        // Fetch first batch of products
        echo "Fetching first batch of products (page 1, size 5)...\n";
        $products_result = $connector->get_products(1, 5);
        echo "✓ Fetched " . count($products_result['items'] ?? array()) . " products\n\n";

        if (!empty($products_result['items'])) {
            echo "Sample product data:\n";
            $first_product = $products_result['items'][0];
            echo "- SKU: " . ($first_product['sku'] ?? 'N/A') . "\n";
            echo "- ID: " . ($first_product['id'] ?? 'N/A') . "\n";
            echo "- Name: " . ($first_product['name'] ?? 'N/A') . "\n";
            echo "- Type: " . ($first_product['type_id'] ?? 'N/A') . "\n";
            echo "- Price: " . ($first_product['price'] ?? 'N/A') . "\n";
            echo "\n";

            // Try to get full product data
            $sku = $first_product['sku'];
            echo "Fetching full product data for SKU: $sku...\n";
            $full_product = $connector->get_product($sku);

            if ($full_product) {
                echo "✓ Full product data fetched\n";
                echo "- Has extension_attributes: " . (isset($full_product['extension_attributes']) ? 'Yes' : 'No') . "\n";
                echo "- Has stock_item: " . (isset($full_product['extension_attributes']['stock_item']) ? 'Yes' : 'No') . "\n";
                echo "- Has media_gallery_entries: " . (isset($full_product['media_gallery_entries']) ? 'Yes (' . count($full_product['media_gallery_entries']) . ' images)' : 'No') . "\n";
                echo "- Has custom_attributes: " . (isset($full_product['custom_attributes']) ? 'Yes (' . count($full_product['custom_attributes']) . ' attributes)' : 'No') . "\n";
                echo "\n";
            } else {
                echo "✗ Failed to fetch full product data\n\n";
            }
        }

        // Test migration with first product
        echo "=== Testing Migration ===\n";
        if (!empty($products_result['items'])) {
            $first_product = $products_result['items'][0];

            echo "Creating product migrator...\n";
            $migrator = new MWM_Migrator_Products(null, $connector);
            echo "✓ Product migrator created\n\n";

            echo "Attempting to migrate product: " . $first_product['sku'] . "...\n";

            // Try to manually trigger migration
            try {
                $reflection = new ReflectionClass($migrator);
                $method = $reflection->getMethod('migrate_product');
                $method->setAccessible(true);

                ob_start();
                $method->invoke($migrator, $first_product);
                $output = ob_get_clean();

                echo "Migration attempt completed\n";
                echo "Output: $output\n";

            } catch (ReflectionException $e) {
                echo "✗ Reflection error: " . $e->getMessage() . "\n";
            } catch (Exception $e) {
                echo "✗ Migration error: " . $e->getMessage() . "\n";
                echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            }
        }

    } else {
        echo "Database connection not configured. Please set API credentials.\n";
    }

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Debug Complete ===\n";
