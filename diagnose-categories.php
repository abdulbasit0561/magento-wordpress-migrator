<?php
/**
 * Category Migration Diagnostic Script
 *
 * This script helps diagnose why categories are not being migrated from Magento.
 * It tests the connector and API connections and shows detailed debug info.
 *
 * Usage: Upload to WordPress wp-admin and access via browser
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator to run this diagnostic.');
}

// Load the connector client
require_once __DIR__ . '/includes/class-mwm-connector-client.php';
require_once __DIR__ . '/includes/class-mwm-api-connector.php';

// Get settings
$settings = get_option('mwm_settings', array());

?>
<!DOCTYPE html>
<html>
<head>
    <title>Category Migration Diagnostic</title>
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
        .label { font-weight: bold; color: #555; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .status-pass { color: #28a745; font-weight: bold; }
        .status-fail { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Magento Category Migration Diagnostic</h1>
        <p>This tool helps diagnose why categories are not being migrated from Magento.</p>

        <?php
        // Step 1: Check settings
        echo '<h2>Step 1: Settings Configuration</h2>';
        echo '<div class="section info">';

        $connection_mode = $settings['connection_mode'] ?? 'connector';
        echo '<p><span class="label">Connection Mode:</span> ' . esc_html($connection_mode) . '</p>';

        if ($connection_mode === 'connector') {
            $connector_url = $settings['connector_url'] ?? '';
            $connector_api_key = $settings['connector_api_key'] ?? '';

            echo '<p><span class="label">Connector URL:</span> ' . ($connector_url ? esc_html($connector_url) : '<span class="status-fail">NOT SET</span>') . '</p>';
            echo '<p><span class="label">API Key:</span> ' . ($connector_api_key ? 'SET (' . esc_html(substr($connector_api_key, 0, 8)) . '...)' : '<span class="status-fail">NOT SET</span>') . '</p>';

            if (empty($connector_url) || empty($connector_api_key)) {
                echo '<p class="status-fail">❌ Connector credentials not configured. Please go to Settings and configure the connector.</p>';
                echo '</div></div></body></html>';
                exit;
            }
            echo '<p class="status-pass">✓ Connector credentials configured</p>';
        }
        echo '</div>';

        // Step 2: Test basic connection
        echo '<h2>Step 2: Connection Test</h2>';
        echo '<div class="section info">';

        if ($connection_mode === 'connector') {
            try {
                $connector = new MWM_Connector_Client($connector_url, $connector_api_key);
                $ping_result = $connector->ping();

                if ($ping_result['success']) {
                    echo '<p class="status-pass">✓ Ping successful - Connector is accessible</p>';
                } else {
                    echo '<p class="status-fail">❌ Ping failed: ' . esc_html($ping_result['message'] ?? 'Unknown error') . '</p>';
                    echo '<p>The connector URL may be incorrect or the connector file is not deployed on the Magento server.</p>';
                    echo '</div></div></body></html>';
                    exit;
                }
            } catch (Exception $e) {
                echo '<p class="status-fail">❌ Connection error: ' . esc_html($e->getMessage()) . '</p>';
                echo '</div></div></body></html>';
                exit;
            }
        }
        echo '</div>';

        // Step 3: Test authentication
        echo '<h2>Step 3: Authentication Test</h2>';
        echo '<div class="section info">';

        if ($connection_mode === 'connector') {
            try {
                $debug_result = $connector->test_debug();

                if (is_wp_error($debug_result)) {
                    echo '<p class="status-fail">❌ Authentication failed: ' . esc_html($debug_result->get_error_message()) . '</p>';
                    echo '<p>The API key may be incorrect. Please check the connector settings.</p>';
                    echo '</div></div></body></html>';
                    exit;
                }

                echo '<p class="status-pass">✓ Authentication successful</p>';
                echo '<p>Connector debug info:</p><pre>';
                print_r($debug_result);
                echo '</pre>';
            } catch (Exception $e) {
                echo '<p class="status-fail">❌ Authentication error: ' . esc_html($e->getMessage()) . '</p>';
                echo '</div></div></body></html>';
                exit;
            }
        }
        echo '</div>';

        // Step 4: Test Magento connection
        echo '<h2>Step 4: Magento Connection Test</h2>';
        echo '<div class="section info">';

        if ($connection_mode === 'connector') {
            try {
                $result = $connector->test_connection();

                if ($result['success']) {
                    echo '<p class="status-pass">✓ Magento connection successful</p>';
                    echo '<p><span class="label">Magento Version:</span> ' . esc_html($result['magento_version'] ?? 'Unknown') . '</p>';
                } else {
                    echo '<p class="status-fail">❌ Magento connection failed: ' . esc_html($result['message']) . '</p>';
                    echo '<p>This error indicates the connector file cannot load Magento.</p>';
                    echo '<p><strong>Possible causes:</strong></p>';
                    echo '<ul>';
                    echo '<li>The connector file is not in the Magento root directory</li>';
                    echo '<li>Magento bootstrap files are not accessible</li>';
                    echo '<li>PHP permissions issues</li>';
                    echo '</ul>';
                    echo '</div></div></body></html>';
                    exit;
                }
            } catch (Exception $e) {
                echo '<p class="status-fail">❌ Magento connection error: ' . esc_html($e->getMessage()) . '</p>';
                echo '</div></div></body></html>';
                exit;
            }
        }
        echo '</div>';

        // Step 5: Test categories count
        echo '<h2>Step 5: Categories Count Test</h2>';
        echo '<div class="section info">';

        if ($connection_mode === 'connector') {
            try {
                $count_result = $connector->get_categories_count();

                if (is_wp_error($count_result)) {
                    echo '<p class="status-fail">❌ Error getting categories count: ' . esc_html($count_result->get_error_message()) . '</p>';
                } else {
                    echo '<p class="status-pass">✓ Categories count: ' . esc_html($count_result) . '</p>';

                    if ($count_result == 0) {
                        echo '<p class="warning">⚠️ WARNING: Magento reports 0 active categories!</p>';
                        echo '<p><strong>Possible causes:</strong></p>';
                        echo '<ul>';
                        echo '<li>No categories exist in Magento</li>';
                        echo '<li>All categories are disabled (is_active = 0)</li>';
                        echo '<li>Category collection filter is too restrictive</li>';
                        echo '</ul>';
                    }
                }
            } catch (Exception $e) {
                echo '<p class="status-fail">❌ Error: ' . esc_html($e->getMessage()) . '</p>';
            }
        }
        echo '</div>';

        // Step 6: Test categories fetch
        echo '<h2>Step 6: Categories Fetch Test (CRITICAL)</h2>';
        echo '<div class="section info">';

        if ($connection_mode === 'connector') {
            try {
                $categories_result = $connector->get_categories();

                if (is_wp_error($categories_result)) {
                    echo '<p class="status-fail">❌ Error fetching categories: ' . esc_html($categories_result->get_error_message()) . '</p>';
                } else {
                    echo '<p><span class="label">Response type:</span> ' . gettype($categories_result) . '</p>';
                    echo '<p><span class="label">Response keys:</span> ' . esc_html(implode(', ', array_keys($categories_result))) . '</p>';

                    if (isset($categories_result['success'])) {
                        echo '<p><span class="label">Success:</span> ' . ($categories_result['success'] ? 'true' : 'false') . '</p>';
                    }

                    if (isset($categories_result['categories'])) {
                        echo '<p class="status-pass">✓ Categories array found in response</p>';
                        echo '<p><span class="label">Number of categories:</span> ' . count($categories_result['categories']) . '</p>';

                        if (count($categories_result['categories']) > 0) {
                            echo '<p class="status-pass">✓ Categories ARE being returned from Magento!</p>';
                            echo '<p>This means the connector is working correctly.</p>';
                            echo '<h3>First category sample:</h3><pre>';
                            print_r($categories_result['categories'][0]);
                            echo '</pre>';

                            echo '<div class="success">';
                            echo '<p><strong>The issue is NOT in the connector!</strong></p>';
                            echo '<p>Since categories ARE being fetched from Magento, the problem must be in:</p>';
                            echo '<ol>';
                            echo '<li>The category migration process in WordPress</li>';
                            echo '<li>WordPress cron not running properly</li>';
                            echo '<li>PHP execution timeout during migration</li>';
                            echo '<li>WordPress/WooCommerce term creation errors</li>';
                            echo '</ol>';
                            echo '<p>Check the error logs for WordPress to see what happens during the migration.</p>';
                            echo '</div>';
                        } else {
                            echo '<p class="status-fail">❌ Categories array is EMPTY!</p>';
                            echo '<p>The connector returned success=0 categories.</p>';
                            echo '<p><strong>This is the root cause!</strong> Categories cannot be migrated because none are being returned.</p>';
                            echo '<h3>Full Response:</h3><pre>';
                            print_r($categories_result);
                            echo '</pre>';
                        }
                    } else {
                        echo '<p class="status-fail">❌ No "categories" key in response!</p>';
                        echo '<p><strong>This is the root cause!</strong> The connector is not returning the expected data structure.</p>';
                        echo '<h3>Full Response:</h3><pre>';
                        print_r($categories_result);
                        echo '</pre>';

                        echo '<div class="warning">';
                        echo '<p><strong>Expected response format:</strong></p>';
                        echo '<pre>{
    "success": true,
    "categories": [...],
    "count": 123
}</pre>';
                        echo '</div>';
                    }

                    if (isset($categories_result['count'])) {
                        echo '<p><span class="label">Count:</span> ' . esc_html($categories_result['count']) . '</p>';
                    }

                    if (isset($categories_result['message'])) {
                        echo '<p><span class="label">Message:</span> ' . esc_html($categories_result['message']) . '</p>';
                    }
                }
            } catch (Exception $e) {
                echo '<p class="status-fail">❌ Exception: ' . esc_html($e->getMessage()) . '</p>';
                echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
            }
        }
        echo '</div>';

        // Step 7: Check WordPress logs
        echo '<h2>Step 7: Recent WordPress Errors</h2>';
        echo '<div class="section info">';
        echo '<p>Check your WordPress debug.log file for recent errors related to categories migration.</p>';
        echo '<p><strong>Debug log location:</strong> ' . esc_html(WP_CONTENT_DIR . '/debug.log') . '</p>';

        if (file_exists(WP_CONTENT_DIR . '/debug.log')) {
            echo '<p><strong>Last 50 lines of debug log:</strong></p>';
            $debug_log = file_get_contents(WP_CONTENT_DIR . '/debug.log');
            $lines = explode("\n", $debug_log);
            $recent_lines = array_slice($lines, -50);
            echo '<pre>' . esc_html(implode("\n", $recent_lines)) . '</pre>';
        } else {
            echo '<p class="warning">⚠️ Debug log file not found. WP_DEBUG may be disabled.</p>';
        }
        echo '</div>';

        // Summary
        echo '<h2>Summary & Next Steps</h2>';
        echo '<div class="section">';
        echo '<h3>Common Issues & Solutions:</h3>';
        echo '<table>';
        echo '<tr><th>Issue</th><th>Solution</th></tr>';
        echo '<tr><td>Connector file not deployed</td><td>Upload magento-connector.php to your Magento root directory</td></tr>';
        echo '<tr><td>Categories array empty</td><td>Check if categories exist in Magento and are enabled</td></tr>';
        echo '<tr><td>Wrong response format</td><td>Update magento-connector.php to return correct format</td></tr>';
        echo '<tr><td>Categories fetch OK but migration fails</td><td>Check WordPress logs, may be cron/timeout issue</td></tr>';
        echo '</table>';
        echo '</div>';
        ?>

        <p><a href="<?php echo admin_url('admin.php?page=magento-wp-migrator'); ?>">← Back to Migrator Settings</a></p>
    </div>
</body>
</html>
