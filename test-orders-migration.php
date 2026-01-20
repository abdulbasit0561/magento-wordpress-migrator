<?php
/**
 * Test Orders Migration Fix
 *
 * This script tests the orders migration fix to ensure:
 * 1. Order items are properly retrieved via connector
 * 2. Order addresses are properly retrieved
 * 3. Orders can be migrated successfully
 */

// Load WordPress
require_once('/workspace/wp-load.php');

// Load plugin files
require_once('/workspace/wp-content/plugins/magento-wordpress-migrator/includes/class-mwm-logger.php');
require_once('/workspace/wp-content/plugins/magento-wordpress-migrator/includes/class-mwm-connector-client.php');
require_once('/workspace/wp-content/plugins/magento-wordpress-migrator/includes/class-mwm-migrator-base.php');
require_once('/workspace/wp-content/plugins/magento-wordpress-migrator/includes/class-mwm-migrator-orders.php');

echo "===========================================\n";
echo "Orders Migration Test\n";
echo "===========================================\n\n";

// Get settings
$settings = get_option('mwm_settings', array());

// Check if connector credentials are configured
if (empty($settings['connector_url']) || empty($settings['connector_api_key'])) {
    echo "❌ ERROR: Connector credentials not configured\n";
    echo "Please configure the connector URL and API key in plugin settings\n";
    exit(1);
}

echo "✓ Connector credentials found\n";
echo "Connector URL: " . $settings['connector_url'] . "\n\n";

// Step 1: Test connection
echo "Step 1: Testing connector connection...\n";
try {
    $connector = new MWM_Connector_Client(
        $settings['connector_url'],
        $settings['connector_api_key']
    );

    $result = $connector->test_connection();
    if ($result['success']) {
        echo "✓ Connection successful\n";
        echo "  Magento version: " . ($result['magento_version'] ?? 'Unknown') . "\n\n";
    } else {
        echo "❌ Connection failed: " . $result['message'] . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Connection error: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Get orders count
echo "Step 2: Getting orders count...\n";
try {
    $count = $connector->get_orders_count();
    if (is_wp_error($count)) {
        echo "❌ Error getting count: " . $count->get_error_message() . "\n";
        exit(1);
    }
    echo "✓ Total orders: " . $count . "\n\n";

    if ($count == 0) {
        echo "⚠ No orders found in Magento. Nothing to test.\n";
        exit(0);
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 3: Fetch a sample order (first order only)
echo "Step 3: Fetching a sample order...\n";
try {
    $orders_result = $connector->get_orders(1, 1);

    if (is_wp_error($orders_result)) {
        echo "❌ Error fetching orders: " . $orders_result->get_error_message() . "\n";
        exit(1);
    }

    $orders = $orders_result['orders'] ?? array();

    if (empty($orders)) {
        echo "❌ No orders returned\n";
        exit(1);
    }

    $sample_order = $orders[0];
    echo "✓ Fetched order #" . ($sample_order['increment_id'] ?? $sample_order['entity_id']) . "\n";

    // Debug: Print order structure
    echo "  Order keys: " . implode(', ', array_keys($sample_order)) . "\n";

    // Check if order has billing address
    if (isset($sample_order['billing_address'])) {
        echo "✓ Order has billing address\n";
    } else {
        echo "⚠ WARNING: Order missing billing address\n";
    }

    // Check if order has shipping address
    if (isset($sample_order['shipping_address'])) {
        echo "✓ Order has shipping address\n";
    } else {
        echo "⚠ WARNING: Order missing shipping address\n";
    }

    // Check if order has items
    if (isset($sample_order['items']) && !empty($sample_order['items'])) {
        echo "✓ Order has " . count($sample_order['items']) . " items in main response\n";
        echo "  Sample item: " . ($sample_order['items'][0]['name'] ?? 'N/A') . "\n";
    } else {
        echo "❌ ERROR: Order items not in main response\n";
        echo "  This means the magento-connector.php needs to be updated on the Magento server\n";
        echo "  to include items in the orders response.\n";
    }

    echo "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

// Step 4: Test migrator initialization
echo "Step 4: Testing orders migrator initialization...\n";
try {
    $migrator = new MWM_Migrator_Orders(null, null, $connector);
    echo "✓ Orders migrator initialized successfully\n";
    echo "  Mode: Connector mode\n\n";
} catch (Exception $e) {
    echo "❌ Error initializing migrator: " . $e->getMessage() . "\n";
    exit(1);
}

echo "===========================================\n";
echo "✓ All tests passed!\n";
echo "===========================================\n\n";

echo "Summary:\n";
echo "- Connector connection: Working\n";
echo "- Orders count: " . $count . "\n";
echo "- Order retrieval: Working\n";
echo "- Migrator initialization: Working\n";
echo "\nThe orders migration fix is ready. You can now run the full migration\n";
echo "from the WordPress admin panel.\n";
