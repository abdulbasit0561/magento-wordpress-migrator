<?php
/**
 * Connector Communication Test Script
 *
 * This script helps diagnose connector communication issues by:
 * 1. Testing the connector URL directly
 * 2. Showing the raw response
 * 3. Validating JSON structure
 * 4. Displaying detailed error information
 *
 * Usage: Upload to WordPress root and visit /test-connector-communication.php
 */

// WordPress bootstrap
require_once dirname(__FILE__) . '/wp-load.php';

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. Administrators only.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Connector Communication Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; }
        .test-section { background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f4f4f4; border: 1px solid #ddd; padding: 10px; overflow-x: auto; font-size: 12px; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f4f4f4; }
        .label { font-weight: bold; display: inline-block; width: 150px; }
    </style>
</head>
<body>
    <h1>🔌 Magento Connector Communication Test</h1>

    <div class="info">
        <strong>ℹ️ About this tool:</strong> This diagnostic tool tests communication between WordPress and the Magento connector file.
        It will show you exactly what's being sent and received, helping identify JSON parsing issues.
    </div>

    <?php
    // Get connector settings
    $settings = get_option('mwm_settings', array());
    $connector_url = $settings['connector_url'] ?? '';
    $connector_api_key = $settings['connector_api_key'] ?? '';

    if (empty($connector_url) || empty($connector_api_key)) {
        echo '<div class="error"><strong>❌ Configuration Missing:</strong> Please configure the connector URL and API key in WordPress settings first.</div>';
        echo '<p><a href="' . admin_url('admin.php?page=magento-wp-migrator-settings') . '">Go to Settings Page</a></p>';
        exit;
    }
    ?>

    <div class="test-section">
        <h2>📋 Current Configuration</h2>
        <table>
            <tr>
                <th>Setting</th>
                <th>Value</th>
            </tr>
            <tr>
                <td><strong>Connector URL</strong></td>
                <td><code><?php echo esc_html($connector_url); ?></code></td>
            </tr>
            <tr>
                <td><strong>API Key</strong></td>
                <td><code><?php echo esc_html(substr($connector_api_key, 0, 8)); ?>...<?php echo esc_html(substr($connector_api_key, -4)); ?></code></td>
            </tr>
        </table>
    </div>

    <?php
    // Test 1: Check if connector file is accessible
    echo '<div class="test-section">';
    echo '<h2>Test 1: Connector File Accessibility</h2>';

    $test_url = $connector_url . '?endpoint=test';
    echo '<p><span class="label">Test URL:</span> <code>' . esc_html($test_url) . '</code></p>';

    $response = wp_remote_get($test_url, array(
        'timeout' => 30,
        'headers' => array(
            'X-Magento-Connector-Key' => $connector_api_key,
            'Accept' => 'application/json'
        ),
        'sslverify' => false
    ));

    if (is_wp_error($response)) {
        echo '<div class="error"><strong>❌ Request Failed:</strong> ' . esc_html($response->get_error_message()) . '</div>';
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        echo '<p><span class="label">HTTP Status:</span> <code>' . esc_html($response_code) . '</code></p>';

        if ($response_code == 200) {
            echo '<div class="success"><strong>✅ Connector file is accessible</strong></div>';
        } else {
            echo '<div class="warning"><strong>⚠️ Unexpected HTTP Status:</strong> Expected 200, got ' . esc_html($response_code) . '</div>';
        }
    }
    echo '</div>';

    // Test 2: Check response headers
    echo '<div class="test-section">';
    echo '<h2>Test 2: Response Headers</h2>';

    if (!is_wp_error($response)) {
        $headers = wp_remote_retrieve_headers($response);
        echo '<table>';
        echo '<tr><th>Header</th><th>Value</th></tr>';

        foreach ($headers as $key => $value) {
            echo '<tr>';
            echo '<td><code>' . esc_html($key) . '</code></td>';
            echo '<td><code>' . esc_html($value) . '</code></td>';
            echo '</tr>';
        }

        echo '</table>';

        if (isset($headers['content-type'])) {
            if (strpos($headers['content-type'], 'application/json') !== false) {
                echo '<div class="success"><strong>✅ Correct Content-Type:</strong> application/json</div>';
            } else {
                echo '<div class="warning"><strong>⚠️ Wrong Content-Type:</strong> Expected application/json, got ' . esc_html($headers['content-type']) . '</div>';
            }
        }
    }
    echo '</div>';

    // Test 3: Check raw response body
    echo '<div class="test-section">';
    echo '<h2>Test 3: Raw Response Body</h2>';

    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $body_length = strlen($body);

        echo '<p><span class="label">Response Length:</span> ' . number_format($body_length) . ' bytes</p>';

        if ($body_length > 0) {
            echo '<p><strong>Raw Response (first 1000 chars):</strong></p>';
            echo '<pre>' . esc_html(substr($body, 0, 1000)) . ($body_length > 1000 ? "\n... (truncated)" : "") . '</pre>';
        } else {
            echo '<div class="error"><strong>❌ Empty Response:</strong> The connector returned an empty response.</div>';
        }
    }
    echo '</div>';

    // Test 4: JSON validation
    echo '<div class="test-section">';
    echo '<h2>Test 4: JSON Validation</h2>';

    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);

        echo '<p><span class="label">JSON Decode Test:</span> ';
        $data = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            echo '<span style="color: green;">✅ Valid JSON</span></p>';

            echo '<div class="success"><strong>✅ JSON is valid!</strong></div>';

            echo '<h3>Decoded JSON Structure:</h3>';
            echo '<pre>' . esc_html(print_r($data, true)) . '</pre>';

            // Check expected structure
            if (isset($data['success'])) {
                echo '<div class="success"><strong>✅ Has "success" field:</strong> ' . ($data['success'] ? 'true' : 'false') . '</div>';

                if (isset($data['message'])) {
                    echo '<div class="success"><strong>✅ Has "message" field:</strong> ' . esc_html($data['message']) . '</div>';
                }

                if (isset($data['magento_version'])) {
                    echo '<div class="success"><strong>✅ Has "magento_version" field:</strong> ' . esc_html($data['magento_version']) . '</div>';
                }
            } else {
                echo '<div class="error"><strong>❌ Missing "success" field in JSON response</strong></div>';
            }
        } else {
            echo '<span style="color: red;">❌ Invalid JSON</span></p>';

            echo '<div class="error"><strong>❌ JSON Error:</strong> ' . json_last_error_msg() . '</div>';

            echo '<h3>Error Details:</h3>';
            echo '<table>';
            echo '<tr><td><strong>Error Code:</strong></td><td>' . json_last_error() . ' (see http://php.net/manual/en/json.constants.php)</td></tr>';
            echo '<tr><td><strong>Error Message:</strong></td><td>' . json_last_error_msg() . '</td></tr>';
            echo '</table>';

            echo '<h3>Potential Issues:</h3>';
            echo '<ul>';
            echo '<li>PHP warnings or notices appearing before JSON</li>';
            echo '<li>Magento initialization producing output</li>';
            echo '<li>Whitespace before <code>&lt;?php</code> tag in connector file</li>';
            echo '<li>BOM (Byte Order Mark) in connector file</li>';
            echo '<li>Extra characters or HTML mixed with JSON</li>';
            echo '</ul>';

            echo '<h3>Check Magento Connector Logs:</h3>';
            echo '<p>Look for errors in: <code>/path/to/magento/var/log/connector-errors.log</code></p>';
        }
    }
    echo '</div>';

    // Test 5: Recommendations
    echo '<div class="test-section">';
    echo '<h2>📝 Recommendations</h2>';

    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($data['success']) && $data['success']) {
            echo '<div class="success">';
            echo '<h3>✅ All Tests Passed!</h3>';
            echo '<p>The connector is working correctly. You can now use it for migration.</p>';
            echo '<p><a href="' . admin_url('admin.php?page=magento-wp-migrator') . '" class="button button-primary">Go to Migration Page</a></p>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<h3>❌ Issues Detected</h3>';
            echo '<p><strong>Next Steps:</strong></p>';
            echo '<ol>';
            echo '<li>Check the <strong>Raw Response Body</strong> section above to see what the connector is returning</li>';
            echo '<li>Look for PHP errors, warnings, or HTML in the response</li>';
            echo '<li>Check Magento error logs: <code>/var/log/connector-errors.log</code></li>';
            echo '<li>Check WordPress debug logs: <code>/wp-content/debug.log</code></li>';
            echo '<li>Ensure the magento-connector.php file is in the correct location</li>';
            echo '<li>Verify the API key matches exactly</li>';
            echo '</ol>';
            echo '</div>';
        }
    }

    echo '</div>';

    // Test 6: WordPress Debug Log Check
    echo '<div class="test-section">';
    echo '<h2>Test 5: WordPress Debug Log (Recent Entries)</h2>';

    $debug_log = WP_CONTENT_DIR . '/debug.log';

    if (file_exists($debug_log)) {
        // Read last 50 lines of debug log
        $lines = file($debug_log);
        $recent_lines = array_slice($lines, -50);

        echo '<p><strong>Recent MWM Connector log entries:</strong></p>';
        echo '<pre>';

        $found_connector_logs = false;
        foreach (array_reverse($recent_lines) as $line) {
            if (stripos($line, 'MWM Connector') !== false || stripos($line, 'MAGENTO CONNECTOR') !== false) {
                echo esc_html($line);
                $found_connector_logs = true;
            }
        }

        if (!$found_connector_logs) {
            echo '<em>No connector-related log entries found in recent logs.</em>';
        }

        echo '</pre>';
    } else {
        echo '<div class="warning"><strong>⚠️ Debug log not found:</strong> ' . esc_html($debug_log) . '</div>';
        echo '<p>To enable debug logging, add <code>define( \'WP_DEBUG\', true );</code> and <code>define( \'WP_DEBUG_LOG\', true );</code> to your wp-config.php file.</p>';
    }

    echo '</div>';
    ?>

    <div class="test-section">
        <h2>🔧 Helpful Links</h2>
        <ul>
            <li><a href="<?php echo admin_url('admin.php?page=magento-wp-migrator-settings'); ?>">Connector Settings</a></li>
            <li><a href="<?php echo admin_url('admin.php?page=magento-wp-migrator'); ?>">Migration Dashboard</a></li>
            <li><a href="https://github.com/php/doc-en/blob/master/reference/json/constants.xml" target="_blank">JSON Error Constants Reference</a></li>
        </ul>
    </div>

    <div class="info">
        <p><strong>Need more help?</strong> Check the following log files:</p>
        <ul>
            <li>WordPress: <code><?php echo WP_CONTENT_DIR; ?>/debug.log</code></li>
            <li>Magento: <code>/path/to/magento/var/log/connector-errors.log</code></li>
            <li>Magento Access: <code>/path/to/magento/var/log/connector-access.log</code></li>
        </ul>
    </div>
</body>
</html>
