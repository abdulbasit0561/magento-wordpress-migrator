<?php
/**
 * Direct Category Migration Test
 *
 * Tests the category migration process directly without going through AJAX/cron
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator to run this test.');
}

// Load required classes
require_once __DIR__ . '/includes/class-mwm-connector-client.php';
require_once __DIR__ . '/includes/class-mwm-migrator-categories.php';

// Get settings
$settings = get_option('mwm_settings', array());

?>
<!DOCTYPE html>
<html>
<head>
    <title>Category Migration Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 3px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
        .status-pass { color: #28a745; font-weight: bold; }
        .status-fail { color: #dc3545; font-weight: bold; }
        button { background: #0073aa; color: white; border: none; padding: 10px 20px; border-radius: 3px; cursor: pointer; font-size: 14px; }
        button:hover { background: #005a87; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Direct Category Migration Test</h1>
        <p>This test will fetch categories from Magento and try to create them in WordPress directly.</p>

        <?php
        // Step 1: Test WooCommerce
        echo '<h2>Step 1: WooCommerce Check</h2>';
        echo '<div class="section info">';

        if (!class_exists('WooCommerce')) {
            echo '<p class="status-fail">❌ WooCommerce is NOT active!</p>';
            echo '<p>WooCommerce must be active for category migration to work.</p>';
            echo '</div></div></body></html>';
            exit;
        }
        echo '<p class="status-pass">✓ WooCommerce is active</p>';

        // Check if product_cat taxonomy exists
        if (!taxonomy_exists('product_cat')) {
            echo '<p class="status-fail">❌ product_cat taxonomy does not exist!</p>';
            echo '</div></div></body></html>';
            exit;
        }
        echo '<p class="status-pass">✓ product_cat taxonomy exists</p>';
        echo '</div>';

        // Step 2: Get settings
        echo '<h2>Step 2: Connection Settings</h2>';
        echo '<div class="section info">';

        $connection_mode = $settings['connection_mode'] ?? 'connector';
        echo '<p><span class="label">Connection Mode:</span> ' . esc_html($connection_mode) . '</p>';

        if ($connection_mode === 'connector') {
            $connector_url = $settings['connector_url'] ?? '';
            $connector_api_key = $settings['connector_api_key'] ?? '';

            if (empty($connector_url) || empty($connector_api_key)) {
                echo '<p class="status-fail">❌ Connector credentials not configured</p>';
                echo '</div></div></body></html>';
                exit;
            }

            echo '<p><span class="label">Connector URL:</span> ' . esc_html($connector_url) . '</p>';
            echo '<p><span class="label">API Key:</span> ' . esc_html(substr($connector_api_key, 0, 8)) . '...</p>';
        }
        echo '</div>';

        // Step 3: Fetch categories from Magento
        echo '<h2>Step 3: Fetch Categories from Magento</h2>';
        echo '<div class="section info">';

        try {
            $connector = new MWM_Connector_Client($connector_url, $connector_api_key);
            $categories_result = $connector->get_categories();

            if (is_wp_error($categories_result)) {
                echo '<p class="status-fail">❌ Error: ' . esc_html($categories_result->get_error_message()) . '</p>';
                echo '</div></div></body></html>';
                exit;
            }

            if (!isset($categories_result['categories'])) {
                echo '<p class="status-fail">❌ No "categories" key in response</p>';
                echo '<pre>' . esc_html(print_r($categories_result, true)) . '</pre>';
                echo '</div></div></body></html>';
                exit;
            }

            $categories = $categories_result['categories'];
            echo '<p class="status-pass">✓ Fetched ' . count($categories) . ' categories from Magento</p>';

            if (count($categories) == 0) {
                echo '<p class="warning">⚠️ No categories to migrate!</p>';
                echo '</div></div></body></html>';
                exit;
            }

            echo '<h3>First 3 categories:</h3><pre>';
            print_r(array_slice($categories, 0, 3));
            echo '</pre>';
        } catch (Exception $e) {
            echo '<p class="status-fail">❌ Exception: ' . esc_html($e->getMessage()) . '</p>';
            echo '</div></div></body></html>';
            exit;
        }
        echo '</div>';

        // Step 4: Test creating a single category
        echo '<h2>Step 4: Test Creating a Single Category</h2>';
        echo '<div class="section info">';

        $test_category = $categories[0];
        echo '<p>Testing with category: <strong>' . esc_html($test_category['name']) . '</strong> (ID: ' . esc_html($test_category['id']) . ')</p>';

        // Check if category already exists
        $existing_term = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'meta_key' => '_magento_category_id',
            'meta_value' => $test_category['id']
        ));

        if (!empty($existing_term) && !is_wp_error($existing_term)) {
            echo '<p class="warning">⚠️ Category already exists in WordPress</p>';
            echo '<p>Term ID: ' . $existing_term[0]->term_id . '</p>';
        } else {
            echo '<p>Category does not exist yet, attempting to create...</p>';

            // Try to create the category
            $name = $test_category['name'];
            $slug = !empty($test_category['url_key']) ? sanitize_title($test_category['url_key']) : sanitize_title($name);

            echo '<p><span class="label">Name:</span> ' . esc_html($name) . '</p>';
            echo '<p><span class="label">Slug:</span> ' . esc_html($slug) . '</p>';

            $result = wp_insert_term($name, 'product_cat', array(
                'slug' => $slug,
                'description' => $test_category['description'] ?? ''
            ));

            if (is_wp_error($result)) {
                echo '<p class="status-fail">❌ Failed to create category!</p>';
                echo '<p>Error: ' . esc_html($result->get_error_message()) . '</p>';

                $error_data = $result->get_error_data();
                if ($error_data) {
                    echo '<pre>' . esc_html(print_r($error_data, true)) . '</pre>';
                }
            } else {
                echo '<p class="status-pass">✓ Category created successfully!</p>';
                echo '<p><span class="label">Term ID:</span> ' . $result['term_id'] . '</p>';
                echo '<p><span class="label">Term Taxonomy ID:</span> ' . $result['term_taxonomy_id'] . '</p>';

                // Store Magento category ID
                update_term_meta($result['term_id'], '_magento_category_id', $test_category['id']);
                echo '<p class="status-pass">✓ Magento category ID stored</p>';
            }
        }
        echo '</div>';

        // Step 5: Run full migration (if requested)
        if (isset($_POST['run_migration'])) {
            echo '<h2>Step 5: Running Full Category Migration</h2>';
            echo '<div class="section info">';
            echo '<p>Starting migration of all ' . count($categories) . ' categories...</p>';

            try {
                // Create migrator instance
                $migrator = new MWM_Migrator_Categories(null, null, $connector);

                // Run the migration
                $stats = $migrator->run();

                echo '<p class="status-pass">✓ Migration completed!</p>';
                echo '<p>Total: ' . $stats['total'] . '</p>';
                echo '<p>Processed: ' . $stats['processed'] . '</p>';
                echo '<p class="status-pass">Successful: ' . $stats['successful'] . '</p>';

                if ($stats['failed'] > 0) {
                    echo '<p class="status-fail">Failed: ' . $stats['failed'] . '</p>';
                }

                // Get any errors
                $migration_data = get_option('mwm_current_migration', array());
                if (!empty($migration_data['errors'])) {
                    echo '<h3>Errors:</h3><pre>';
                    print_r($migration_data['errors']);
                    echo '</pre>';
                }

            } catch (Exception $e) {
                echo '<p class="status-fail">❌ Migration failed: ' . esc_html($e->getMessage()) . '</p>';
                echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
            }
            echo '</div>';
        }

        // Step 6: Check existing categories in WordPress
        echo '<h2>Step 6: Existing Categories in WordPress</h2>';
        echo '<div class="section info">';

        $existing_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'number' => 10
        ));

        echo '<p>Total product_cat terms in WordPress: ' . wp_count_terms('product_cat', array('hide_empty' => false)) . '</p>';

        if (!empty($existing_categories) && !is_wp_error($existing_categories)) {
            echo '<h3>First 10 categories:</h3>';
            echo '<table>';
            echo '<tr><th>Term ID</th><th>Name</th><th>Slug</th><th>Magento ID</th></tr>';
            foreach ($existing_categories as $cat) {
                $magento_id = get_term_meta($cat->term_id, '_magento_category_id', true);
                echo '<tr>';
                echo '<td>' . $cat->term_id . '</td>';
                echo '<td>' . esc_html($cat->name) . '</td>';
                echo '<td>' . esc_html($cat->slug) . '</td>';
                echo '<td>' . ($magento_id ? $magento_id : '-') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        echo '</div>';

        // Step 7: Migration check
        echo '<h2>Step 7: Migration Status Check</h2>';
        echo '<div class="section info">';

        $migration_data = get_option('mwm_current_migration', array());
        if (!empty($migration_data)) {
            echo '<h3>Current Migration Data:</h3><pre>';
            print_r($migration_data);
            echo '</pre>';
        } else {
            echo '<p>No active migration data found.</p>';
        }

        // Check cron status
        echo '<h3>WP-Cron Status:</h3>';
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            echo '<p class="status-fail">❌ WP-Cron is DISABLED via DISABLE_WP_CRON constant</p>';
            echo '<p>This means migrations will not run automatically!</p>';
        } else {
            echo '<p class="status-pass">✓ WP-Cron is enabled</p>';
        }

        // Check scheduled events
        $next_scheduled = wp_next_scheduled('mwm_process_migration');
        if ($next_scheduled) {
            echo '<p>Next scheduled migration: ' . date('Y-m-d H:i:s', $next_scheduled) . '</p>';
        } else {
            echo '<p>No migration events scheduled.</p>';
        }
        echo '</div>';
        ?>

        <h2>Actions</h2>
        <div class="section">
            <form method="post">
                <button type="submit" name="run_migration" value="1">
                    🚀 Run Full Category Migration Now
                </button>
            </form>
            <p><small>This will attempt to migrate all categories from Magento to WordPress.</small></p>
        </div>

        <p><a href="<?php echo admin_url('admin.php?page=magento-wp-migrator'); ?>">← Back to Migrator Settings</a></p>
    </div>
</body>
</html>
