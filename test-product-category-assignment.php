<?php
/**
 * Product Category Assignment Test
 *
 * Tests if categories are being correctly assigned to products
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator to run this test.');
}

require_once __DIR__ . '/includes/class-mwm-connector-client.php';

// Get settings
$settings = get_option('mwm_settings', array());
$connector_url = $settings['connector_url'] ?? '';
$connector_api_key = $settings['connector_api_key'] ?? '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Product Category Assignment Test</title>
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
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .status-pass { color: #28a745; font-weight: bold; }
        .status-fail { color: #dc3545; font-weight: bold; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .badge-green { background: #d4edda; color: #155724; }
        .badge-red { background: #f8d7da; color: #721c24; }
        .badge-gray { background: #e2e3e5; color: #383d41; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Product Category Assignment Test</h1>
        <p>Tests if categories are being correctly assigned to products during migration.</p>

        <?php
        // Step 1: Check if categories exist in WordPress
        echo '<h2>Step 1: WordPress Categories Check</h2>';
        echo '<div class="section info">';

        $wp_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'meta_key' => '_magento_category_id'
        ));

        $categories_with_magento_id = 0;
        $categories_without_magento_id = 0;
        $magento_id_map = array(); // Map Magento ID -> WordPress Term ID

        foreach ($wp_categories as $cat) {
            $magento_id = get_term_meta($cat->term_id, '_magento_category_id', true);
            if ($magento_id) {
                $categories_with_magento_id++;
                $magento_id_map[$magento_id] = $cat->term_id;
            } else {
                $categories_without_magento_id++;
            }
        }

        $total_categories = count($wp_categories);
        echo '<p><strong>Total product_cat terms:</strong> ' . $total_categories . '</p>';
        echo '<p><span class="badge badge-green">With Magento ID:</span> ' . $categories_with_magento_id . '</p>';
        echo '<p><span class="badge badge-gray">Without Magento ID:</span> ' . $categories_without_magento_id . '</p>';

        if ($categories_with_magento_id > 0) {
            echo '<p class="status-pass">✓ Categories have been migrated and have Magento IDs stored</p>';
        } else {
            echo '<p class="error">❌ No categories found with Magento IDs!</p>';
            echo '<p><strong>You must migrate categories BEFORE products for category assignment to work.</strong></p>';
        }

        // Show sample categories with Magento IDs
        if ($categories_with_magento_id > 0) {
            echo '<h3>Sample Categories with Magento IDs:</h3>';
            echo '<table>';
            echo '<tr><th>WP Term ID</th><th>Magento ID</th><th>Name</th><th>Slug</th></tr>';
            $count = 0;
            foreach ($wp_categories as $cat) {
                if ($count++ >= 5) break;
                $magento_id = get_term_meta($cat->term_id, '_magento_category_id', true);
                if ($magento_id) {
                    echo '<tr>';
                    echo '<td>' . $cat->term_id . '</td>';
                    echo '<td>' . $magento_id . '</td>';
                    echo '<td>' . esc_html($cat->name) . '</td>';
                    echo '<td>' . esc_html($cat->slug) . '</td>';
                    echo '</tr>';
                }
            }
            echo '</table>';
        }

        echo '</div>';

        // Step 2: Test category lookup function
        echo '<h2>Step 2: Category Lookup Test</h2>';
        echo '<div class="section info">';

        if (!empty($magento_id_map)) {
            // Test a few Magento category IDs to verify lookup works
            echo '<h3>Testing Category Lookup Function:</h3>';
            echo '<table>';
            echo '<tr><th>Magento ID</th><th>Lookup Result</th><th>Status</th></tr>';

            $test_ids = array_slice(array_keys($magento_id_map), 0, 3);
            foreach ($test_ids as $magento_id) {
                // Simulate the get_category_by_magento_id function
                $terms = get_terms(array(
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                    'meta_key' => '_magento_category_id',
                    'meta_value' => $magento_id
                ));

                if (!empty($terms) && !is_wp_error($terms)) {
                    $wp_id = $terms[0]->term_id;
                    echo '<tr>';
                    echo '<td>' . $magento_id . '</td>';
                    echo '<td>WordPress Term ID: ' . $wp_id . '</td>';
                    echo '<td class="status-pass">✓ Found</td>';
                    echo '</tr>';
                } else {
                    echo '<tr>';
                    echo '<td>' . $magento_id . '</td>';
                    echo '<td>-</td>';
                    echo '<td class="status-fail">❌ Not found</td>';
                    echo '</tr>';
                }
            }
            echo '</table>';
        }

        echo '</div>';

        // Step 3: Fetch products from Magento connector
        echo '<h2>Step 3: Magento Product Categories</h2>';
        echo '<div class="section info">';
        echo '<p>Fetching a sample product from Magento to check category_ids...</p>';

        try {
            $connector = new MWM_Connector_Client($connector_url, $connector_api_key);
            $products_result = $connector->get_products(5, 1); // Get 5 products from page 1

            if (is_wp_error($products_result)) {
                echo '<p class="status-fail">❌ Error: ' . esc_html($products_result->get_error_message()) . '</p>';
            } else {
                $products = $products_result['products'] ?? array();

                if (empty($products)) {
                    echo '<p class="warning">⚠️ No products returned from connector</p>';
                } else {
                    echo '<h3>Sample Products with Category IDs:</h3>';
                    echo '<table>';
                    echo '<tr><th>Product SKU</th><th>Magento Category IDs</th><th>Match in WP?</th></tr>';

                    foreach ($products as $product) {
                        $sku = $product['sku'] ?? 'unknown';
                        $category_ids = $product['category_ids'] ?? array();

                        echo '<tr>';
                        echo '<td>' . esc_html($sku) . '</td>';
                        echo '<td>' . (empty($category_ids) ? '<em>None</em>' : implode(', ', $category_ids)) . '</td>';

                        // Check if these category IDs exist in WordPress
                        if (empty($category_ids)) {
                            echo '<td class="status-fail">❌ No categories assigned in Magento</td>';
                        } else {
                            $found_count = 0;
                            $missing_count = 0;
                            foreach ($category_ids as $cat_id) {
                                if (isset($magento_id_map[$cat_id])) {
                                    $found_count++;
                                } else {
                                    $missing_count++;
                                }
                            }

                            if ($found_count > 0 && $missing_count === 0) {
                                echo '<td class="status-pass">✓ All ' . $found_count . ' categories found</td>';
                            } elseif ($found_count > 0) {
                                echo '<td class="warning">⚠️ ' . $found_count . ' found, ' . $missing_count . ' missing</td>';
                            } else {
                                echo '<td class="status-fail">❌ None found in WordPress</td>';
                            }
                        }
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            }
        } catch (Exception $e) {
            echo '<p class="status-fail">❌ Exception: ' . esc_html($e->getMessage()) . '</p>';
        }

        echo '</div>';

        // Step 4: Check actual WooCommerce products
        echo '<h2>Step 4: WooCommerce Products Category Assignment</h2>';
        echo '<div class="section info">';

        $wc_products = wc_get_products(array(
            'limit' => 10,
            'return' => 'ids'
        ));

        if (empty($wc_products)) {
            echo '<p>No WooCommerce products found yet. Products need to be migrated first.</p>';
        } else {
            echo '<h3>First 10 WooCommerce Products and Their Categories:</h3>';
            echo '<table>';
            echo '<tr><th>Product ID</th><th>SKU</th><th>Product Name</th><th>Categories</th></tr>';

            foreach ($wc_products as $product_id) {
                $product = wc_get_product($product_id);
                if (!$product) continue;

                $sku = $product->get_sku();
                $name = $product->get_name();
                $categories = wp_get_post_terms($product_id, 'product_cat');
                $category_names = array();
                foreach ($categories as $cat) {
                    $category_names[] = $cat->name;
                }

                echo '<tr>';
                echo '<td>' . $product_id . '</td>';
                echo '<td>' . esc_html($sku ?: 'No SKU') . '</td>';
                echo '<td>' . esc_html($name) . '</td>';
                echo '<td>' . (empty($category_names) ? '<span class="badge badge-red">No categories</span>' : implode(', ', array_map('esc_html', $category_names))) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }

        echo '</div>';

        // Step 5: Root Cause Analysis
        echo '<h2>Step 5: Root Cause Analysis</h2>';
        echo '<div class="section">';

        if ($categories_with_magento_id == 0) {
            echo '<h3>❌ ISSUE FOUND: Categories Not Migrated</h3>';
            echo '<p><strong>Problem:</strong> Categories have not been migrated to WordPress yet.</p>';
            echo '<p><strong>Solution:</strong> Migrate categories BEFORE products.</p>';
            echo '<ol>';
            echo '<li>Go to Magento → Migrator → Migration</li>';
            echo '<li>Select "Categories" as migration type</li>';
            echo '<li>Run the category migration first</li>';
            echo '<li>Then run the product migration</li>';
            echo '</ol>';
        } elseif ($categories_without_magento_id > 0) {
            echo '<h3>⚠️ WARNING: Some Categories Missing Magento ID</h3>';
            echo '<p><strong>Problem:</strong> ' . $categories_without_magento_id . ' categories don\'t have their Magento ID stored.</p>';
            echo '<p><strong>Possible Cause:</strong> Categories were created manually or migrated before the fix was applied.</p>';
            echo '<p><strong>Solution:</strong> Re-migrate categories to ensure proper Magento ID mapping.</p>';
        } else {
            echo '<p class="status-pass">✓ Category migration looks good. All categories have Magento IDs stored.</p>';
        }

        echo '</div>';
        ?>

        <p><a href="<?php echo admin_url('admin.php?page=magento-wp-migrator'); ?>">← Back to Migrator Settings</a></p>
    </div>
</body>
</html>
