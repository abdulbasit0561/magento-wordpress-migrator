<?php
/**
 * Diagnostic Script for Products Migration Hang Issue
 *
 * This script helps diagnose why the products migration gets stuck at 48%
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Check if user is logged in
if (!is_user_logged_in()) {
    die('Access denied. Please log in to WordPress first.');
}

// Check permissions
if (!current_user_can('manage_options')) {
    die('Permission denied. You need administrator privileges.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Products Migration Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #23282d; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 30px; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa; }
        .success { background: #f7fff7; border-left-color: #46b450; }
        .error { background: #fff7f7; border-left-color: #dc3232; }
        .warning { background: #fff8e5; border-left-color: #ffb900; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #0073aa; color: white; }
        .test-button { padding: 10px 20px; background: #0073aa; color: white; border: none; cursor: pointer; margin: 5px; }
        .test-button:hover { background: #005a87; }
        .log { font-family: monospace; font-size: 12px; background: #f5f5f5; padding: 10px; max-height: 400px; overflow-y: auto; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .stat-card { background: #f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; }
        .stat-value { font-size: 24px; font-weight: bold; color: #0073aa; }
        .stat-label { color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Products Migration Diagnostics</h1>
        <p>This tool helps diagnose issues with products migration getting stuck at 48%.</p>

        <?php
        // Get current migration status
        $current_migration = get_option('mwm_current_migration', array());
        $settings = get_option('mwm_settings', array());

        // Display current migration status
        echo '<h2>Current Migration Status</h2>';
        echo '<div class="section">';
        if (!empty($current_migration)) {
            echo '<table>';
            echo '<tr><th>Property</th><th>Value</th></tr>';
            foreach ($current_migration as $key => $value) {
                if ($key === 'errors') {
                    $value = count($value) . ' errors';
                }
                echo '<tr><td><strong>' . esc_html($key) . '</strong></td><td>' . esc_html(print_r($value, true)) . '</td></tr>';
            }
            echo '</table>';

            // Calculate percentage
            if (!empty($current_migration['total']) && $current_migration['total'] > 0) {
                $percentage = round(($current_migration['processed'] / $current_migration['total']) * 100, 1);
                echo '<p class="' . ($percentage >= 100 ? 'success' : ($percentage < 50 ? 'error' : 'warning')) . '">';
                echo '<strong>Progress: ' . $percentage . '%</strong>';
                echo '</p>';
            }
        } else {
            echo '<p>No migration currently in progress.</p>';
        }
        echo '</div>';

        // Display connection settings
        echo '<h2>Connection Settings</h2>';
        echo '<div class="section">';
        echo '<table>';
        echo '<tr><th>Setting</th><th>Value</th></tr>';
        echo '<tr><td>Connection Mode</td><td>' . esc_html($settings['connection_mode'] ?? 'not set') . '</td></tr>';
        echo '<tr><td>Connector URL</td><td>' . esc_html($settings['connector_url'] ?? 'not set') . '</td></tr>';
        echo '<tr><td>Store URL (API)</td><td>' . esc_html($settings['store_url'] ?? 'not set') . '</td></tr>';
        echo '<tr><td>DB Host</td><td>' . esc_html($settings['db_host'] ?? 'not set') . '</td></tr>';
        echo '<tr><td>DB Name</td><td>' . esc_html($settings['db_name'] ?? 'not set') . '</td></tr>';
        echo '</table>';
        echo '</div>';

        // Test Connector Connection
        echo '<h2>Connector Tests</h2>';

        $test_results = array();

        // Test 1: Ping
        echo '<div class="section">';
        echo '<h3>Test 1: Connector Ping (No Auth)</h3>';
        if (!empty($settings['connector_url'])) {
            try {
                require_once MWM_PLUGIN_DIR . 'includes/class-mwm-connector-client.php';
                $connector = new MWM_Connector_Client(
                    $settings['connector_url'],
                    $settings['connector_api_key'] ?? ''
                );
                $ping_result = $connector->ping();

                if ($ping_result['success'] ?? false) {
                    echo '<p class="success">✅ Ping successful: ' . esc_html($ping_result['message'] ?? 'OK') . '</p>';
                    $test_results['ping'] = true;
                } else {
                    echo '<p class="error">❌ Ping failed: ' . esc_html($ping_result['message'] ?? 'Unknown error') . '</p>';
                    $test_results['ping'] = false;
                }
            } catch (Exception $e) {
                echo '<p class="error">❌ Exception: ' . esc_html($e->getMessage()) . '</p>';
                $test_results['ping'] = false;
            }
        } else {
            echo '<p class="warning">⚠️ Connector URL not configured</p>';
            $test_results['ping'] = null;
        }
        echo '</div>';

        // Test 2: Products Count
        echo '<div class="section">';
        echo '<h3>Test 2: Get Products Count</h3>';
        if (!empty($settings['connector_url']) && !empty($settings['connector_api_key'])) {
            try {
                require_once MWM_PLUGIN_DIR . 'includes/class-mwm-connector-client.php';
                $connector = new MWM_Connector_Client(
                    $settings['connector_url'],
                    $settings['connector_api_key']
                );
                $count = $connector->get_products_count();

                if (!is_wp_error($count)) {
                    echo '<p class="success">✅ Total products: <strong>' . intval($count) . '</strong></p>';
                    $test_results['count'] = $count;
                } else {
                    echo '<p class="error">❌ Error: ' . esc_html($count->get_error_message()) . '</p>';
                    $test_results['count'] = false;
                }
            } catch (Exception $e) {
                echo '<p class="error">❌ Exception: ' . esc_html($e->getMessage()) . '</p>';
                $test_results['count'] = false;
            }
        } else {
            echo '<p class="warning">⚠️ Connector credentials not configured</p>';
            $test_results['count'] = null;
        }
        echo '</div>';

        // Test 3: Fetch First Page
        echo '<div class="section">';
        echo '<h3>Test 3: Fetch Products (Page 1, Batch Size 20)</h3>';
        if (!empty($settings['connector_url']) && !empty($settings['connector_api_key'])) {
            try {
                require_once MWM_PLUGIN_DIR . 'includes/class-mwm-connector-client.php';
                $connector = new MWM_Connector_Client(
                    $settings['connector_url'],
                    $settings['connector_api_key']
                );

                error_log('MWM Diagnostics: Fetching products page 1');
                $products_result = $connector->get_products(20, 1);

                if (!is_wp_error($products_result)) {
                    $products = $products_result['products'] ?? array();
                    echo '<p class="success">✅ Retrieved ' . count($products) . ' products</p>';

                    if (!empty($products)) {
                        echo '<h4>Sample Product Data:</h4>';
                        echo '<pre>';
                        echo esc_html(print_r(array_slice($products, 0, 2), true));
                        echo '</pre>';
                    }
                    $test_results['fetch_page1'] = count($products);
                } else {
                    echo '<p class="error">❌ Error: ' . esc_html($products_result->get_error_message()) . '</p>';
                    $test_results['fetch_page1'] = false;
                }
            } catch (Exception $e) {
                echo '<p class="error">❌ Exception: ' . esc_html($e->getMessage()) . '</p>';
                echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
                $test_results['fetch_page1'] = false;
            }
        } else {
            echo '<p class="warning">⚠️ Connector credentials not configured</p>';
            $test_results['fetch_page1'] = null;
        }
        echo '</div>';

        // Test 4: Fetch Multiple Pages (Check for duplicates/empty responses)
        echo '<div class="section">';
        echo '<h3>Test 4: Fetch Multiple Pages (Check for Duplicates)</h3>';
        if (!empty($settings['connector_url']) && !empty($settings['connector_api_key'])) {
            try {
                require_once MWM_PLUGIN_DIR . 'includes/class-mwm-connector-client.php';
                $connector = new MWM_Connector_Client(
                    $settings['connector_url'],
                    $settings['connector_api_key']
                );

                $all_skus = array();
                $empty_batches = 0;
                $max_pages = 5;

                echo '<p>Testing pages 1-' . $max_pages . '...</p>';

                for ($page = 1; $page <= $max_pages; $page++) {
                    error_log("MWM Diagnostics: Fetching page $page");
                    $result = $connector->get_products(20, $page);

                    if (is_wp_error($result)) {
                        echo '<p class="error">❌ Page ' . $page . ' Error: ' . esc_html($result->get_error_message()) . '</p>';
                        break;
                    }

                    $products = $result['products'] ?? array();

                    if (empty($products)) {
                        $empty_batches++;
                        echo '<p class="warning">⚠️ Page ' . $page . ' is empty</p>';
                        if ($empty_batches >= 3) {
                            echo '<p class="warning">⚠️ Stopping: 3 consecutive empty pages</p>';
                            break;
                        }
                    } else {
                        $empty_batches = 0;
                        $page_skus = array();
                        foreach ($products as $product) {
                            $sku = $product['sku'] ?? '';
                            if (!empty($sku)) {
                                if (in_array($sku, $all_skus)) {
                                    echo '<p class="error">❌ DUPLICATE SKU FOUND: ' . esc_html($sku) . ' on page ' . $page . '</p>';
                                }
                                $all_skus[] = $sku;
                                $page_skus[] = $sku;
                            }
                        }
                        echo '<p class="success">✅ Page ' . $page . ': ' . count($products) . ' products (' . count($page_skus) . ' valid SKUs)</p>';
                    }
                }

                echo '<p><strong>Total unique SKUs found: ' . count($all_skus) . '</strong></p>';

                if (count($all_skus) > 0) {
                    echo '<h4>First 10 SKUs:</h4>';
                    echo '<pre>' . esc_html(implode(', ', array_slice($all_skus, 0, 10))) . '</pre>';
                }

                $test_results['multi_page'] = array(
                    'unique_skus' => count($all_skus),
                    'empty_batches' => $empty_batches
                );
            } catch (Exception $e) {
                echo '<p class="error">❌ Exception: ' . esc_html($e->getMessage()) . '</p>';
                $test_results['multi_page'] = false;
            }
        } else {
            echo '<p class="warning">⚠️ Connector credentials not configured</p>';
        }
        echo '</div>';

        // Test 5: Check for existing products in WooCommerce
        echo '<div class="section">';
        echo '<h3>Test 5: Check Existing WooCommerce Products</h3>';
        try {
            $existing_products = get_posts(array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'fields' => 'ids'
            ));

            echo '<p>Total WooCommerce products: <strong>' . count($existing_products) . '</strong></p>';

            if (count($existing_products) > 0) {
                // Check some SKUs
                $sample_ids = array_slice($existing_products, 0, 5);
                echo '<h4>Sample Existing Products:</h4>';
                echo '<table>';
                echo '<tr><th>ID</th><th>SKU</th><th>Title</th></tr>';
                foreach ($sample_ids as $id) {
                    $sku = get_post_meta($id, '_sku', true);
                    $title = get_the_title($id);
                    echo '<tr><td>' . $id . '</td><td>' . esc_html($sku) . '</td><td>' . esc_html($title) . '</td></tr>';
                }
                echo '</table>';
            }

            $test_results['wc_products'] = count($existing_products);
        } catch (Exception $e) {
            echo '<p class="error">❌ Exception: ' . esc_html($e->getMessage()) . '</p>';
            $test_results['wc_products'] = false;
        }
        echo '</div>';

        // Analysis and Recommendations
        echo '<h2>📊 Analysis & Recommendations</h2>';
        echo '<div class="section">';

        $issues = array();
        $warnings = array();

        // Check if migration is stuck
        if (!empty($current_migration) && $current_migration['status'] === 'processing') {
            $started = strtotime($current_migration['started']);
            $elapsed = time() - $started;
            if ($elapsed > 300) { // 5 minutes
                $issues[] = 'Migration has been running for ' . round($elapsed/60, 1) . ' minutes - may be stuck';
            }
        }

        // Check for test failures
        if (($test_results['ping'] ?? false) === false) {
            $issues[] = 'Connector ping failed - check connector URL and Magento connector endpoint';
        }

        if (($test_results['count'] ?? false) === false) {
            $issues[] = 'Cannot get products count - authentication or connector error';
        }

        if (($test_results['fetch_page1'] ?? false) === false) {
            $issues[] = 'Cannot fetch products page - connector may be returning errors';
        }

        // Check for potential duplicate issues
        if (isset($test_results['multi_page']['unique_skus']) && $test_results['multi_page']['unique_skus'] < ($test_results['count'] ?? 0)) {
            $warnings[] = 'Found fewer unique SKUs than expected - connector may be returning duplicates';
        }

        // Check WC products
        if (isset($test_results['wc_products']) && $test_results['wc_products'] > 0) {
            $warnings[] = 'There are already ' . $test_results['wc_products'] . ' products in WooCommerce - migration may be skipping existing ones';
        }

        if (!empty($issues)) {
            echo '<h3 class="error">❌ Issues Found:</h3>';
            echo '<ul class="error">';
            foreach ($issues as $issue) {
                echo '<li>' . esc_html($issue) . '</li>';
            }
            echo '</ul>';
        }

        if (!empty($warnings)) {
            echo '<h3 class="warning">⚠️ Warnings:</h3>';
            echo '<ul class="warning">';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html($warning) . '</li>';
            }
            echo '</ul>';
        }

        if (empty($issues) && empty($warnings)) {
            echo '<p class="success">✅ No critical issues found!</p>';
        }

        echo '</div>';

        // Action buttons
        echo '<h2>🔧 Actions</h2>';
        echo '<div class="section">';
        echo '<button class="test-button" onclick="if(confirm(\'Clear stuck migration status?\')) { window.location.href=\'?action=clear_migration\'; }">Clear Stuck Migration</button> ';
        echo '<button class="test-button" onclick="if(confirm(\'Reset all WooCommerce products? WARNING: This cannot be undone!\')) { window.location.href=\'?action=reset_products\'; }">Reset WooCommerce Products</button> ';
        echo '<button class="test-button" onclick="window.location.reload()">Refresh Diagnostics</button>';
        echo '</div>';

        // Handle actions
        if (isset($_GET['action'])) {
            $action = sanitize_text_field($_GET['action']);

            if ($action === 'clear_migration') {
                delete_option('mwm_current_migration');
                echo '<div class="section success"><p>✅ Migration status cleared. <a href="">Refresh</a> to see updated status.</p></div>';
            }

            if ($action === 'reset_products') {
                $count = 0;
                $products = get_posts(array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'fields' => 'ids'
                ));

                foreach ($products as $product_id) {
                    wp_delete_post($product_id, true); // Force delete
                    $count++;
                }

                echo '<div class="section success"><p>✅ Deleted ' . $count . ' WooCommerce products. <a href="">Refresh</a> to see updated status.</p></div>';
            }
        }

        // Debug log
        echo '<h2>📝 Recent Debug Logs</h2>';
        echo '<div class="section">';
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file)) {
            $lines = file($log_file);
            $mwm_lines = array();

            // Get last 100 MWM log lines
            foreach (array_reverse($lines) as $line) {
                if (strpos($line, 'MWM') !== false) {
                    $mwm_lines[] = $line;
                    if (count($mwm_lines) >= 100) {
                        break;
                    }
                }
            }

            if (!empty($mwm_lines)) {
                echo '<div class="log">';
                echo implode('', array_reverse($mwm_lines));
                echo '</div>';
            } else {
                echo '<p>No MWM log entries found in debug.log</p>';
            }
        } else {
            echo '<p>Debug log file not found: ' . esc_html($log_file) . '</p>';
        }
        echo '</div>';
        ?>

    </div>
</body>
</html>
