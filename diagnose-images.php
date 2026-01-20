<?php
/**
 * Diagnostic Script for Image Migration Issues
 *
 * This script investigates:
 * 1. Why images aren't being assigned to products
 * 2. Why not all images are being migrated
 *
 * Usage: Upload to WordPress root and visit in browser
 */

// Load WordPress
require_once(dirname(__FILE__) . '/wp-load.php');

// Load plugin files
require_once(dirname(__FILE__) . '/wp-content/plugins/magento-wordpress-migrator/includes/class-mwm-logger.php');
require_once(dirname(__FILE__) . '/wp-content/plugins/magento-wordpress-migrator/includes/class-mwm-connector-client.php');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Image Migration Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1400px; margin: 20px auto; padding: 20px; }
        h1, h2 { color: #333; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .test-section { background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .product-card { background: white; border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f2f2f2; }
        .has-image { background: #d4edda; }
        .no-image { background: #f8d7da; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
    <h1>🔍 Image Migration Diagnostics</h1>
";

// Get settings
$settings = get_option('mwm_settings', array());

if (empty($settings['connector_url']) || empty($settings['connector_api_key'])) {
    echo "<div class='error'>❌ Connector credentials not configured.</div>";
    echo "</body></html>";
    exit;
}

try {
    $connector = new MWM_Connector_Client(
        $settings['connector_url'],
        $settings['connector_api_key']
    );
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
    echo "</body></html>";
    exit;
}

// TEST 1: Check Products from Connector
echo "<div class='test-section'>
    <h2>Test 1: Analyze Product Data from Connector</h2>";

try {
    $products_result = $connector->get_products(10, 1);
    $products = $products_result['products'] ?? array();

    echo "<div class='info'>Fetched " . count($products) . " products for analysis</div>";

    $stats = array(
        'with_media_gallery' => 0,
        'with_image_field' => 0,
        'with_small_image' => 0,
        'with_thumbnail' => 0,
        'no_images_at_all' => 0,
        'total_media_items' => 0
    );

    echo "<table>
        <tr>
            <th>Product</th>
            <th>SKU</th>
            <th>Has media[] array?</th>
            <th>Has image field?</th>
            <th>Has small_image?</th>
            <th>Has thumbnail?</th>
            <th>Media Gallery Count</th>
            <th>Sample Media Data</th>
        </tr>";

    foreach ($products as $product) {
        $has_media = !empty($product['media']) && is_array($product['media']);
        $has_image = !empty($product['image']);
        $has_small = !empty($product['small_image']) && $product['small_image'] !== $product['image'];
        $has_thumbnail = !empty($product['thumbnail']) && $product['thumbnail'] !== $product['image'] && $product['thumbnail'] !== $product['small_image'];
        $media_count = $has_media ? count($product['media']) : 0;

        if ($has_media) $stats['with_media_gallery']++;
        if ($has_image) $stats['with_image_field']++;
        if ($has_small) $stats['with_small_image']++;
        if ($has_thumbnail) $stats['with_thumbnail']++;
        if ($media_count > 0) $stats['total_media_items'] += $media_count;
        if (!$has_media && !$has_image) $stats['no_images_at_all']++;

        $row_class = ($has_media || $has_image) ? 'has-image' : 'no-image';

        echo "<tr class='$row_class'>
            <td>" . htmlspecialchars($product['name'] ?? 'Unknown') . "</td>
            <td>" . htmlspecialchars($product['sku'] ?? 'Unknown') . "</td>
            <td>" . ($has_media ? '✅ Yes (' . $media_count . ')' : '❌ No') . "</td>
            <td>" . ($has_image ? '✅ Yes' : '❌ No') . "</td>
            <td>" . ($has_small ? '✅ Yes' : '❌ No') . "</td>
            <td>" . ($has_thumbnail ? '✅ Yes' : '❌ No') . "</td>
            <td>$media_count</td>
            <td>";

        if ($has_media) {
            $sample = array_slice($product['media'], 0, 1);
            echo "<pre>" . htmlspecialchars(print_r($sample[0], true)) . "</pre>";
        } elseif ($has_image) {
            echo "<code>" . htmlspecialchars(substr($product['image'], 0, 50)) . "...</code>";
        } else {
            echo "<em>No image data</em>";
        }

        echo "</td></tr>";
    }

    echo "</table>";

    echo "<h3>Statistics Summary:</h3>";
    echo "<ul>";
    echo "<li>Products with media[] array: <strong>{$stats['with_media_gallery']}</strong> / " . count($products) . "</li>";
    echo "<li>Products with image field: <strong>{$stats['with_image_field']}</strong> / " . count($products) . "</li>";
    echo "<li>Products with small_image: <strong>{$stats['with_small_image']}</strong> / " . count($products) . "</li>";
    echo "<li>Products with thumbnail: <strong>{$stats['with_thumbnail']}</strong> / " . count($products) . "</li>";
    echo "<li>Products with NO images at all: <strong>{$stats['no_images_at_all']}</strong> / " . count($products) . "</li>";
    echo "<li>Total media gallery items: <strong>{$stats['total_media_items']}</strong></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
}

echo "</div>";

// TEST 2: Check WordPress Products
echo "<div class='test-section'>
    <h2>Test 2: Analyze WordPress Products</h2>";

$args = array(
    'post_type' => 'product',
    'posts_per_page' => 20,
    'orderby' => 'date',
    'order' => 'DESC'
);

$wp_products = wc_get_products($args);

echo "<div class='info'>Found " . count($wp_products) . " products in WordPress</div>";

$wp_stats = array(
    'with_featured_image' => 0,
    'with_gallery' => 0,
    'no_images' => 0,
    'gallery_total' => 0
);

echo "<table>
    <tr>
        <th>Product ID</th>
        <th>SKU</th>
        <th>Name</th>
        <th>Featured Image?</th>
        <th>Gallery Count</th>
        <th>Gallery IDs</th>
    </tr>";

foreach ($wp_products as $product) {
    $product_id = $product->get_id();
    $sku = $product->get_sku();
    $name = $product->get_name();
    $featured_id = get_post_thumbnail_id($product_id);
    $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
    $gallery_array = !empty($gallery_ids) ? explode(',', $gallery_ids) : array();
    $gallery_count = count($gallery_array);

    if ($featured_id) $wp_stats['with_featured_image']++;
    if ($gallery_count > 0) $wp_stats['with_gallery']++;
    if (!$featured_id && $gallery_count == 0) $wp_stats['no_images']++;
    $wp_stats['gallery_total'] += $gallery_count;

    $row_class = ($featured_id || $gallery_count > 0) ? 'has-image' : 'no-image';

    echo "<tr class='$row_class'>
        <td>$product_id</td>
        <td>" . htmlspecialchars($sku) . "</td>
        <td>" . htmlspecialchars(substr($name, 0, 30)) . "</td>
        <td>" . ($featured_id ? "✅ Yes (ID: $featured_id)" : "❌ No") . "</td>
        <td>$gallery_count</td>
        <td>" . ($gallery_count > 0 ? htmlspecialchars($gallery_ids) : "—") . "</td>
    </tr>";
}

echo "</table>";

echo "<h3>WordPress Statistics:</h3>";
echo "<ul>";
echo "<li>Products with featured image: <strong>{$wp_stats['with_featured_image']}</strong> / " . count($wp_products) . "</li>";
echo "<li>Products with gallery images: <strong>{$wp_stats['with_gallery']}</strong> / " . count($wp_products) . "</li>";
echo "<li>Products with NO images: <strong>{$wp_stats['no_images']}</strong> / " . count($wp_products) . "</li>";
echo "<li>Total gallery images: <strong>{$wp_stats['gallery_total']}</strong></li>";
echo "</ul>";

echo "</div>";

// TEST 3: Check for Missing Images
echo "<div class='test-section'>
    <h2>Test 3: Identify Products with Missing Images</h2>";

$missing_images = array();

foreach ($wp_products as $product) {
    $product_id = $product->get_id();
    $sku = $product->get_sku();
    $featured_id = get_post_thumbnail_id($product_id);
    $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
    $gallery_array = !empty($gallery_ids) ? explode(',', $gallery_ids) : array();

    if (!$featured_id && empty($gallery_array)) {
        $missing_images[] = array(
            'id' => $product_id,
            'sku' => $sku,
            'name' => $product->get_name()
        );
    }
}

if (!empty($missing_images)) {
    echo "<div class='warning'>⚠️ Found " . count($missing_images) . " products without any images!</div>";
    echo "<table>
        <tr>
            <th>Product ID</th>
            <th>SKU</th>
            <th>Name</th>
        </tr>";
    foreach ($missing_images as $missing) {
        echo "<tr>
            <td>{$missing['id']}</td>
            <td>" . htmlspecialchars($missing['sku']) . "</td>
            <td>" . htmlspecialchars($missing['name']) . "</td>
        </tr>";
    }
    echo "</table>";
} else {
    echo "<div class='success'>✅ All recent products have images!</div>";
}

echo "</div>";

// TEST 4: Detailed Analysis of One Product
echo "<div class='test-section'>
    <h2>Test 4: Detailed Analysis of Product from Connector</h2>";

try {
    // Get first product's SKU
    if (!empty($products)) {
        $test_product = $products[0];
        $sku = $test_product['sku'] ?? '';

        echo "<h3>Product: " . htmlspecialchars($test_product['name'] ?? 'Unknown') . " (SKU: $sku)</h3>";

        echo "<h4>Raw Product Data (first 2000 chars):</h4>";
        echo "<pre>" . htmlspecialchars(print_r($test_product, true)) . "</pre>";

        // Check if we can get full product data
        if (!empty($sku)) {
            echo "<h4>Full Product from Connector (by SKU):</h4>";
            $full_product = $connector->get_product($sku);

            if (!is_wp_error($full_product)) {
                echo "<pre>" . htmlspecialchars(print_r($full_product, true)) . "</pre>";

                // Analyze media structure
                echo "<h4>Media Analysis:</h4>";
                echo "<ul>";
                echo "<li><strong>Has media[] array:</strong> " . (isset($full_product['media']) ? 'Yes (' . count($full_product['media']) . ' items)' : 'No') . "</li>";
                echo "<li><strong>Has image field:</strong> " . (isset($full_product['image']) ? 'Yes' : 'No') . "</li>";
                echo "<li><strong>Has small_image field:</strong> " . (isset($full_product['small_image']) ? 'Yes' : 'No') . "</li>";
                echo "<li><strong>Has thumbnail field:</strong> " . (isset($full_product['thumbnail']) ? 'Yes' : 'No') . "</li>";

                if (isset($full_product['media']) && is_array($full_product['media'])) {
                    echo "<li><strong>First media item:</strong></li>";
                    echo "<pre>" . htmlspecialchars(print_r($full_product['media'][0], true)) . "</pre>";
                }
                echo "</ul>";
            } else {
                echo "<div class='error'>❌ Failed to get full product: " . $full_product->get_error_message() . "</div>";
            }
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
}

echo "</div>";

// Summary and Recommendations
echo "<div class='test-section'>
    <h2>📋 Summary & Recommendations</h2>
    <div class='info'>
        <h3>Issue Analysis:</h3>
        <ol>
            <li><strong>Connector Data:</strong> Check if products from connector have media[] array or individual image fields</li>
            <li><strong>WordPress Products:</strong> Check if products have featured image and gallery images</li>
            <li><strong>Missing Images:</strong> Identify products that should have images but don't</li>
        </ol>

        <h3>Possible Issues:</h3>
        <ul>
            <li>Connector not returning media[] array - check connector implementation</li>
            <li>Image path construction incorrect - check media_url settings</li>
            <li>Image download failing - check error logs</li>
            <li>Images not being assigned to gallery - check migrate_product_images() function</li>
        </ul>

        <h3>Next Steps:</h3>
        <ol>
            <li>Review the data above to identify which issue is occurring</li>
            <li>Check connector implementation if media[] array is missing</li>
            <li>Review image migration code in class-mwm-migrator-products.php</li>
            <li>Run test-image-migration.php for detailed image testing</li>
        </ol>
    </div>
</div>

<div style='text-align: center; margin-top: 40px; color: #666;'>
    <p>Image Migration Diagnostics - Magento to WordPress Migrator</p>
</div>

</body>
</html>";
