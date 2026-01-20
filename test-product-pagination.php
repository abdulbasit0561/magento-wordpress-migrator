<?php
/**
 * Product Migration Pagination Test
 *
 * Tests the product pagination to diagnose why only 207/415 products are migrated
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator to run this test.');
}

// Load required classes
require_once __DIR__ . '/includes/class-mwm-connector-client.php';

// Get settings
$settings = get_option('mwm_settings', array());
$connection_mode = $settings['connection_mode'] ?? 'connector';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Product Pagination Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 3px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 11px; max-height: 400px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .status-pass { color: #28a745; font-weight: bold; }
        .status-fail { color: #dc3545; font-weight: bold; }
        .page-info { display: inline-block; margin: 5px; padding: 10px; background: #e9ecef; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Product Pagination Diagnostic</h1>
        <p>This tool tests the product pagination to find why only 207/415 products are being migrated.</p>

        <?php
        // Get connection settings
        $connector_url = $settings['connector_url'] ?? '';
        $connector_api_key = $settings['connector_api_key'] ?? '';

        if (empty($connector_url) || empty($connector_api_key)) {
            echo '<div class="section error"><p>❌ Connector credentials not configured</p></div></div></body></html>';
            exit;
        }

        // Step 1: Get total count
        echo '<h2>Step 1: Total Products Count</h2>';
        echo '<div class="section info">';

        try {
            $connector = new MWM_Connector_Client($connector_url, $connector_api_key);
            $total_count = $connector->get_products_count();

            if (is_wp_error($total_count)) {
                echo '<p class="status-fail">❌ Error getting count: ' . esc_html($total_count->get_error_message()) . '</p>';
            } else {
                echo '<p><span class="label">Total products reported by Magento:</span> <strong>' . intval($total_count) . '</strong></p>';

                if ($total_count < 415) {
                    echo '<p class="warning">⚠️ WARNING: Magento reports fewer products than expected (415)</p>';
                    echo '<p>This could mean:</p>';
                    echo '<ul>';
                    echo '<li>Some products are disabled (status = disabled)</li>';
                    echo '<li>Some products are not visible in the current store/website</li>';
                    echo '<li>There are website or store filters applied</li>';
                    echo '</ul>';
                }
            }
        } catch (Exception $e) {
            echo '<p class="status-fail">❌ Exception: ' . esc_html($e->getMessage()) . '</p>';
        }
        echo '</div>';

        // Step 2: Test pagination
        echo '<h2>Step 2: Pagination Test</h2>';
        echo '<div class="section info">';
        echo '<p>Fetching products page by page to see when we stop getting results...</p>';

        $batch_size = 20;
        $max_pages = 30; // Test up to 30 pages (600 products)
        $total_products_found = 0;
        $all_skus = array();
        $page_results = array();
        $consecutive_empty_pages = 0;
        $first_empty_page = 0;

        for ($page = 1; $page <= $max_pages; $page++) {
            $result = $connector->get_products($batch_size, $page);

            if (is_wp_error($result)) {
                $page_results[$page] = array('status' => 'error', 'message' => $result->get_error_message());
                $consecutive_empty_pages++;
                if ($consecutive_empty_pages === 1) $first_empty_page = $page;
                if ($consecutive_empty_pages >= 3) break;
                continue;
            }

            $products = $result['products'] ?? array();
            $page_products_count = count($products);
            $total_products_found += $page_products_count;

            $page_results[$page] = array(
                'status' => 'ok',
                'count' => $page_products_count,
                'total_returned' => $result['total'] ?? 'N/A',
                'page' => $result['page'] ?? $page,
                'limit' => $result['limit'] ?? $batch_size
            );

            // Track SKUs to check for duplicates
            foreach ($products as $product) {
                $sku = $product['sku'] ?? 'unknown';
                if (isset($all_skus[$sku])) {
                    $page_results[$page]['duplicates'][] = $sku;
                }
                $all_skus[$sku] = true;
            }

            if ($page_products_count === 0) {
                $consecutive_empty_pages++;
                if ($consecutive_empty_pages === 1) $first_empty_page = $page;
                if ($consecutive_empty_pages >= 3) {
                    echo '<p class="warning">⚠️ Stopped at page ' . $page . ' after 3 consecutive empty pages</p>';
                    break;
                }
            } else {
                $consecutive_empty_pages = 0;
            }
        }

        echo '<h3>Page-by-Page Results:</h3>';
        echo '<table>';
        echo '<tr><th>Page</th><th>Products</th><th>Total (from connector)</th><th>Status</th></tr>';

        foreach ($page_results as $page_num => $page_data) {
            $status_class = $page_data['status'] === 'ok' ? 'status-pass' : 'status-fail';
            $row_class = $page_data['count'] === 0 ? ' style="background: #fff3cd;"' : '';
            echo '<tr' . $row_class . '>';
            echo '<td>' . $page_num . '</td>';
            echo '<td>' . ($page_data['count'] ?? 0) . '</td>';
            echo '<td>' . ($page_data['total_returned'] ?? 'N/A') . '</td>';
            echo '<td class="' . $status_class . '">' . $page_data['status'] . '</td>';
            echo '</tr>';

            if (!empty($page_data['duplicates'])) {
                echo '<tr><td colspan="4" style="color: #dc3545;">⚠️ Duplicates: ' . implode(', ', $page_data['duplicates']) . '</td></tr>';
            }
            if (!empty($page_data['message'])) {
                echo '<tr><td colspan="4" style="color: #dc3545;">' . esc_html($page_data['message']) . '</td></tr>';
            }
        }
        echo '</table>';

        echo '<h3>Summary:</h3>';
        echo '<p><strong>Total unique products found:</strong> ' . count($all_skus) . '</p>';
        echo '<p><strong>Total product records fetched:</strong> ' . $total_products_found . '</p>';

        if (count($all_skus) < 415) {
            echo '<p class="error">❌ Found ' . count($all_skus) . ' products, but Magento has 415 products!</p>';
            echo '<p><strong>This confirms the issue is in the connector/Magento side.</strong></p>';
            echo '<p>Only ' . round(count($all_skus) / 415 * 100) . '% of products are being returned.</p>';
        }

        if ($total_products_found > count($all_skus)) {
            echo '<p class="warning">⚠️ Duplicate products detected! (' . ($total_products_found - count($all_skus)) . ' duplicates)</p>';
        }

        echo '</div>';

        // Step 3: Check for duplicates
        echo '<h2>Step 3: Duplicate Check</h2>';
        echo '<div class="section info">';
        if ($total_products_found > count($all_skus)) {
            echo '<p class="status-fail">❌ Duplicates found in pagination!</p>';
            echo '<p>This indicates that the pagination is returning the same products on multiple pages.</p>';
            echo '<p><strong>Unique SKUs:</strong> ' . count($all_skus) . '<br>';
            echo '<strong>Total records:</strong> ' . $total_products_found . '<br>';
            echo '<strong>Duplicates:</strong> ' . ($total_products_found - count($all_skus)) . '</p>';
        } else {
            echo '<p class="status-pass">✓ No duplicates found</p>';
        }
        echo '</div>';

        // Step 4: First and last product samples
        echo '<h2>Step 4: Product Samples</h2>';
        echo '<div class="section info">';

        // Get first page
        $first_page = $connector->get_products($batch_size, 1);
        $first_products = $first_page['products'] ?? array();

        echo '<h3>First 5 products (Page 1):</h3>';
        echo '<table>';
        echo '<tr><th>ID</th><th>SKU</th><th>Name</th><th>Status</th><th>Visibility</th></tr>';
        $count = 0;
        foreach ($first_products as $product) {
            if ($count++ >= 5) break;
            echo '<tr>';
            echo '<td>' . esc_html($product['id'] ?? '') . '</td>';
            echo '<td>' . esc_html($product['sku'] ?? '') . '</td>';
            echo '<td>' . esc_html($product['name'] ?? '') . '</td>';
            echo '<td>' . esc_html($product['status'] ?? '') . '</td>';
            echo '<td>' . esc_html($product['visibility'] ?? '') . '</td>';
            echo '</tr>';
        }
        echo '</table>';

        // Get last page with products
        $last_page_with_products = 0;
        foreach ($page_results as $page_num => $data) {
            if ($data['count'] ?? 0 > 0) {
                $last_page_with_products = $page_num;
            }
        }

        if ($last_page_with_products > 1) {
            $last_page = $connector->get_products($batch_size, $last_page_with_products);
            $last_products = $last_page['products'] ?? array();

            echo '<h3>Last 5 products (Page ' . $last_page_with_products . '):</h3>';
            echo '<table>';
            echo '<tr><th>ID</th><th>SKU</th><th>Name</th><th>Status</th><th>Visibility</th></tr>';
            $count = 0;
            foreach (array_slice($last_products, -5) as $product) {
                echo '<tr>';
                echo '<td>' . esc_html($product['id'] ?? '') . '</td>';
                echo '<td>' . esc_html($product['sku'] ?? '') . '</td>';
                echo '<td>' . esc_html($product['name'] ?? '') . '</td>';
                echo '<td>' . esc_html($product['status'] ?? '') . '</td>';
                echo '<td>' . esc_html($product['visibility'] ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }

        echo '</div>';

        // Step 5: Root Cause Analysis
        echo '<h2>Step 5: Root Cause Analysis</h2>';
        echo '<div class="section">';
        echo '<h3>Most Likely Causes:</h3>';

        if (count($all_skus) < 415) {
            echo '<ol>';
            echo '<li><strong>Store/Website Filter:</strong> The connector might be using a specific store ID that only has access to some products.</li>';
            echo '<li><strong>Status Filter:</strong> Some products might be disabled (status != 1)</li>';
            echo '<li><strong>Visibility Filter:</strong> Some products might have visibility settings that exclude them</li>';
            echo '<li><strong>Multi-Website Setup:</strong> In multi-website Magento setups, products need to be assigned to specific websites</li>';
            echo '</ol>';

            echo '<h3>Recommended Fixes:</h3>';
            echo '<p><strong>Option 1 - Update the connector to not filter by store:</strong></p>';
            echo '<pre>// In magento-connector.php, get_products_magento2():
// Add this to explicitly get all products from all stores:
$collection->setStoreId(0); // 0 = admin store, gets all products
// OR
$collection->addStoreFilter([]); // Empty array = no store filter</pre>';

            echo '<p><strong>Option 2 - Check which products are being filtered:</strong></p>';
            echo '<p>In Magento admin, check:</p>';
            echo '<ul>';
            echo '<li>Catalog → Products</li>';
            echo '<li>Filter by "Status" = Enabled</li>';
            echo '<li>Check how many products are enabled</li>';
            echo '</ul>';

            echo '<p><strong>Option 3 - Check product website assignment:</strong></p>';
            echo '<ul>';
            echo '<li>Edit a product that\'s NOT being migrated</li>';
            echo '<li>Check "Websites" tab in product edit</li>';
            echo '<li>Make sure the correct website is checked</li>';
            echo '</ul>';
        } else {
            echo '<p class="success">✓ All ' . count($all_skus) . ' products are being fetched correctly!</p>';
            echo '<p>The issue is not in the connector. The problem must be in the WordPress migration process itself.</p>';
            echo '<p>Check the WordPress debug.log for errors during migration.</p>';
        }

        echo '</div>';

        // Step 6: Connector debug info
        echo '<h2>Step 6: Connector Configuration</h2>';
        echo '<div class="section info">';
        echo '<p><strong>Connector URL:</strong> ' . esc_html($connector_url) . '</p>';

        // Test the products endpoint directly
        $test_url = $connector_url . '?endpoint=products_count';
        echo '<p><strong>Test URL:</strong> <a href="' . esc_attr($test_url) . '" target="_blank">' . esc_html($test_url) . '</a></p>';
        echo '</div>';
        ?>

        <p><a href="<?php echo admin_url('admin.php?page=magento-wp-migrator'); ?>">← Back to Migrator Settings</a></p>
    </div>
</body>
</html>
