<?php
/**
 * Test Script for Image Migration
 *
 * This script tests the image fetching and migration functionality
 *
 * Usage: Upload to WordPress root and visit in browser
 *       Or run from WP-CLI: wp eval-file test-image-migration.php
 */

// Load WordPress
require_once(dirname(__FILE__) . '/wp-load.php');

// Load plugin files
require_once(dirname(__FILE__) . '/wp-content/plugins/magento-wordpress-migrator/includes/class-mwm-logger.php');
require_once(dirname(__FILE__) . '/wp-content/plugins/magento-wordpress-migrator/includes/class-mwm-connector-client.php');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Image Migration Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; }
        h1, h2 { color: #333; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .test-section { background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .product-card { background: white; border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .image-list { list-style: none; padding: 0; }
        .image-list li { background: #f1f1f1; padding: 10px; margin: 5px 0; border-radius: 3px; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🖼️ Image Migration Test Suite</h1>
";

// Test 1: Check Connector Connection
echo "<div class='test-section'>
    <h2>Test 1: Connector Connection</h2>";

$settings = get_option('mwm_settings', array());

if (empty($settings['connector_url']) || empty($settings['connector_api_key'])) {
    echo "<div class='error'>❌ Connector credentials not configured. Please go to WordPress Admin → Magento → Migrator → Settings</div>";
    echo "</div></body></html>";
    exit;
}

try {
    $connector = new MWM_Connector_Client(
        $settings['connector_url'],
        $settings['connector_api_key']
    );

    $result = $connector->test_connection();

    if ($result['success']) {
        echo "<div class='success'>✅ Connector connection successful!</div>";
        echo "<div class='info'><strong>Magento Version:</strong> " . ($result['magento_version'] ?? 'Unknown') . "</div>";
    } else {
        echo "<div class='error'>❌ Connector connection failed: " . $result['message'] . "</div>";
        echo "</div></body></html>";
        exit;
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
    echo "</div></body></html>";
    exit;
}

echo "</div>";

// Test 2: Fetch Products with Media
echo "<div class='test-section'>
    <h2>Test 2: Fetch Products with Media Gallery</h2>";

try {
    // Fetch a small batch of products (limit to 3 for testing)
    $products_result = $connector->get_products(3, 1);

    if (is_wp_error($products_result)) {
        echo "<div class='error'>❌ Failed to fetch products: " . $products_result->get_error_message() . "</div>";
    } else {
        $products = $products_result['products'] ?? array();
        echo "<div class='success'>✅ Fetched " . count($products) . " products from Magento</div>";

        // Display each product with its media info
        foreach ($products as $index => $product) {
            $sku = $product['sku'] ?? 'Unknown';
            $name = $product['name'] ?? 'Unknown';
            $media = $product['media'] ?? array();
            $image = $product['image'] ?? 'N/A';
            $small_image = $product['small_image'] ?? 'N/A';
            $thumbnail = $product['thumbnail'] ?? 'N/A';

            echo "<div class='product-card'>";
            echo "<h3>Product #" . ($index + 1) . ": $name</h3>";
            echo "<p><strong>SKU:</strong> $sku</p>";

            echo "<h4>Basic Images:</h4>";
            echo "<ul class='image-list'>";
            echo "<li><strong>Base Image:</strong> " . ($image ? '✓' : '✗') . " - $image</li>";
            echo "<li><strong>Small Image:</strong> " . ($small_image ? '✓' : '✗') . " - $small_image</li>";
            echo "<li><strong>Thumbnail:</strong> " . ($thumbnail ? '✓' : '✗') . " - $thumbnail</li>";
            echo "</ul>";

            echo "<h4>Media Gallery (" . count($media) . " images):</h4>";

            if (!empty($media)) {
                echo "<ul class='image-list'>";
                foreach ($media as $media_item) {
                    $file = $media_item['file'] ?? 'Unknown';
                    $label = $media_item['label'] ?? 'No label';
                    $position = $media_item['position'] ?? '?';
                    $disabled = $media_item['disabled'] ? 'Yes' : 'No';
                    $media_type = $media_item['media_type'] ?? 'image';

                    echo "<li>
                        <strong>Position $position:</strong> $file<br>
                        <em>Label:</em> $label |
                        <em>Type:</em> $media_type |
                        <em>Disabled:</em> $disabled
                    </li>";
                }
                echo "</ul>";
            } else {
                echo "<div class='error'>⚠️ No media gallery items found for this product</div>";
            }

            echo "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
}

echo "</div>";

// Test 3: Test Image URL Construction
echo "<div class='test-section'>
    <h2>Test 3: Image URL Construction</h2>";

try {
    $products_result = $connector->get_products(1, 1);
    $products = $products_result['products'] ?? array();

    if (!empty($products)) {
        $product = $products[0];
        $connector_url = rtrim($settings['connector_url'], '/');
        $media_url = $connector_url . '/media/catalog/product';

        echo "<div class='info'><strong>Media URL Base:</strong> $media_url</div>";

        // Test with basic image
        if (!empty($product['image'])) {
            $image_path = $product['image'];
            $full_url = $media_url . '/' . ltrim($image_path, '/');

            echo "<h4>Base Image URL:</h4>";
            echo "<pre>Path: $image_path\nFull URL: $full_url</pre>";

            // Test if URL is accessible
            $response = wp_remote_head($full_url, array('timeout' => 10));
            if (is_wp_error($response)) {
                echo "<div class='error'>❌ URL not accessible: " . $response->get_error_message() . "</div>";
            } else {
                $status = wp_remote_retrieve_response_code($response);
                if ($status == 200) {
                    echo "<div class='success'>✅ URL is accessible!</div>";
                } else {
                    echo "<div class='error'>❌ URL returned status: $status</div>";
                }
            }
        }

        // Test with media gallery
        if (!empty($product['media'])) {
            echo "<h4>Media Gallery URLs (first 3):</h4>";
            $count = 0;
            foreach ($product['media'] as $media_item) {
                if ($count >= 3) break;
                $image_path = $media_item['file'] ?? '';
                if (empty($image_path)) continue;

                $full_url = $media_url . '/' . ltrim($image_path, '/');
                $label = $media_item['label'] ?? 'No label';

                echo "<div style='margin: 10px 0; padding: 10px; background: #f1f1f1; border-radius: 3px;'>";
                echo "<strong>Label:</strong> $label<br>";
                echo "<strong>URL:</strong> <a href='$full_url' target='_blank'>" . htmlspecialchars($full_url) . "</a><br>";

                // Test accessibility
                $response = wp_remote_head($full_url, array('timeout' => 5));
                if (is_wp_error($response)) {
                    echo "<span style='color: red;'>❌ Not accessible</span>";
                } else {
                    $status = wp_remote_retrieve_response_code($response);
                    if ($status == 200) {
                        echo "<span style='color: green;'>✅ Accessible</span>";
                    } else {
                        echo "<span style='color: red;'>❌ Status: $status</span>";
                    }
                }

                echo "</div>";
                $count++;
            }
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
}

echo "</div>";

// Test 4: Check WordPress Media Upload Capability
echo "<div class='test-section'>
    <h2>Test 4: WordPress Media Upload Capability</h2>";

// Check if uploads directory is writable
$upload_dir = wp_upload_dir();
if ($upload_dir['error']) {
    echo "<div class='error'>❌ Upload directory error: " . $upload_dir['error'] . "</div>";
} else {
    echo "<div class='success'>✅ Upload directory is writable</div>";
    echo "<div class='info'>";
    echo "<strong>Upload Directory:</strong> " . $upload_dir['path'] . "<br>";
    echo "<strong>Upload URL:</strong> " . $upload_dir['url'] . "<br>";
    echo "</div>";
}

// Check if required WordPress functions are available
if (function_exists('download_url') && function_exists('media_handle_sideload')) {
    echo "<div class='success'>✅ Required WordPress media functions are available</div>";
} else {
    echo "<div class='error'>❌ Required WordPress media functions are missing</div>";
}

echo "</div>";

// Summary
echo "<div class='test-section'>
    <h2>📋 Summary</h2>
    <div class='info'>
        <h3>Next Steps:</h3>
        <ol>
            <li>Review the test results above to ensure:
                <ul>
                    <li>Connector is connected successfully</li>
                    <li>Products are being fetched with complete media gallery</li>
                    <li>Image URLs are accessible</li>
                    <li>WordPress upload directory is writable</li>
                </ul>
            </li>
            <li>If all tests pass, run a product migration to test the full image migration flow</li>
            <li>Check WordPress error log if any tests fail: <code>wp-content/debug.log</code></li>
        </ol>
    </div>
</div>

<div style='text-align: center; margin-top: 40px; color: #666;'>
    <p>Image Migration Test Script - Magento to WordPress Migrator</p>
</div>

</body>
</html>";
