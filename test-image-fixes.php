<?php
/**
 * Test Script for Image Migration Fixes
 *
 * This script tests the fixes for:
 * 1. Images not being assigned to products
 * 2. Not all images being migrated
 *
 * Usage: Upload to WordPress root and visit in browser
 */

// Load WordPress
$wp_load_path = dirname(dirname(dirname(__FILE__))) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    // Try alternative path
    $wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
}
require_once($wp_load_path);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Image Migration Fixes</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; }
        h1, h2 { color: #333; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .test-section { background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; margin: 20px 0; border-radius: 5px; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>🧪 Test Image Migration Fixes</h1>
";

// Test 1: Check Plugin Files
echo "<div class='test-section'>
    <h2>Test 1: Verify Plugin Files Updated</h2>";

$plugin_file = dirname(__FILE__) . '/includes/class-mwm-migrator-products.php';

if (file_exists($plugin_file)) {
    $content = file_get_contents($plugin_file);

    // Check for fix markers
    $has_fix = strpos($content, 'FIXED VERSION') !== false;
    $has_dedup = strpos($content, 'image_paths_seen') !== false;
    $has_merge = strpos($content, 'array_merge') !== false;
    $has_improved_logging = strpos($content, 'Success: $success_count, Failed: $failed_count') !== false;

    echo "<table>
        <tr>
            <th>Check</th>
            <th>Status</th>
        </tr>
        <tr>
            <td>Plugin file exists</td>
            <td>" . ($content !== false ? '✅ Yes' : '❌ No') . "</td>
        </tr>
        <tr>
            <td>Contains FIXED VERSION marker</td>
            <td>" . ($has_fix ? '✅ Yes' : '❌ No') . "</td>
        </tr>
        <tr>
            <td>Has deduplication logic (image_paths_seen)</td>
            <td>" . ($has_dedup ? '✅ Yes' : '❌ No') . "</td>
        </tr>
        <tr>
            <td>Merges media array with individual image fields</td>
            <td>" . ($has_merge ? '✅ Yes' : '❌ No') . "</td>
        </tr>
        <tr>
            <td>Has improved logging (success/fail counts)</td>
            <td>" . ($has_improved_logging ? '✅ Yes' : '❌ No') . "</td>
        </tr>
    </table>";

    if ($has_fix && $has_dedup && $has_merge && $has_improved_logging) {
        echo "<div class='success'>✅ All fixes are present in the plugin file!</div>";
    } else {
        echo "<div class='error'>❌ Some fixes are missing from the plugin file.</div>";
    }
} else {
    echo "<div class='error'>❌ Plugin file not found!</div>";
}

echo "</div>";

// Test 2: Check WordPress Products
echo "<div class='test-section'>
    <h2>Test 2: Analyze WordPress Products</h2>";

$args = array(
    'post_type' => 'product',
    'posts_per_page' => 50,
    'orderby' => 'date',
    'order' => 'DESC'
);

$wp_products = wc_get_products($args);

echo "<div class='info'>Analyzing " . count($wp_products) . " most recent products</div>";

$stats = array(
    'total' => count($wp_products),
    'with_featured' => 0,
    'with_gallery' => 0,
    'no_images' => 0,
    'total_gallery_images' => 0,
    'avg_gallery_count' => 0
);

foreach ($wp_products as $product) {
    $product_id = $product->get_id();
    $featured_id = get_post_thumbnail_id($product_id);
    $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
    $gallery_array = !empty($gallery_ids) ? explode(',', $gallery_ids) : array();
    $gallery_count = count($gallery_array);

    if ($featured_id) $stats['with_featured']++;
    if ($gallery_count > 0) $stats['with_gallery']++;
    if (!$featured_id && $gallery_count == 0) $stats['no_images']++;
    $stats['total_gallery_images'] += $gallery_count;
}

if ($stats['total'] > 0) {
    $stats['avg_gallery_count'] = round($stats['total_gallery_images'] / $stats['total'], 2);
}

echo "<h3>Image Statistics:</h3>";
echo "<ul>";
echo "<li><strong>Total Products:</strong> {$stats['total']}</li>";
echo "<li><strong>With Featured Image:</strong> {$stats['with_featured']} (" . round($stats['with_featured']/$stats['total']*100, 1) . "%)</li>";
echo "<li><strong>With Gallery Images:</strong> {$stats['with_gallery']} (" . round($stats['with_gallery']/$stats['total']*100, 1) . "%)</li>";
echo "<li><strong>With NO Images:</strong> {$stats['no_images']} (" . round($stats['no_images']/$stats['total']*100, 1) . "%)</li>";
echo "<li><strong>Total Gallery Images:</strong> {$stats['total_gallery_images']}</li>";
echo "<li><strong>Avg Gallery Images Per Product:</strong> {$stats['avg_gallery_count']}</li>";
echo "</ul>";

if ($stats['no_images'] > 0) {
    echo "<div class='warning'>⚠️ {$stats['no_images']} products have no images. These may need image re-migration.</div>";
} else {
    echo "<div class='success'>✅ All recent products have images!</div>";
}

echo "</div>";

// Test 3: Sample Product Details
echo "<div class='test-section'>
    <h2>Test 3: Sample Product Image Details</h2>";

// Show first 5 products with detailed image info
$count = 0;
echo "<table>
    <tr>
        <th>ID</th>
        <th>SKU</th>
        <th>Name</th>
        <th>Featured</th>
        <th>Gallery Count</th>
        <th>Gallery IDs</th>
    </tr>";

foreach ($wp_products as $product) {
    if ($count >= 5) break;

    $product_id = $product->get_id();
    $sku = $product->get_sku();
    $name = $product->get_name();
    $featured_id = get_post_thumbnail_id($product_id);
    $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
    $gallery_array = !empty($gallery_ids) ? explode(',', $gallery_ids) : array();
    $gallery_count = count($gallery_array);

    echo "<tr>
        <td>$product_id</td>
        <td>" . htmlspecialchars($sku) . "</td>
        <td>" . htmlspecialchars(substr($name, 0, 30)) . "</td>
        <td>" . ($featured_id ? "✅ $featured_id" : "❌") . "</td>
        <td>$gallery_count</td>
        <td>" . htmlspecialchars($gallery_ids) . "</td>
    </tr>";

    $count++;
}

echo "</table>";
echo "</div>";

// Test 4: Recommendations
echo "<div class='test-section'>
    <h2>📋 Recommendations</h2>
    <div class='info'>
        <h3>How to Test the Fixes:</h3>
        <ol>
            <li><strong>Verify fixes are applied:</strong> The test above should show all checks as ✅ Yes</li>
            <li><strong>Re-run migration:</strong> Go to WordPress Admin -&gt; Magento -&gt; Migrator -&gt; Migration Page</li>
            <li><strong>Migrate a small batch:</strong> Use &quot;Specific Page&quot; option to test with 10-20 products</li>
            <li><strong>Check results:</strong> After migration, run this test script again to verify images</li>
            <li><strong>Check logs:</strong> Look for improved log messages showing &quot;Success: X, Failed: Y&quot;</li>
        </ol>

        <h3>What the Fixes Do:</h3>
        <ul>
            <li><strong>Fix 1 - Deduplication:</strong> Prevents the same image from being added multiple times when it appears in both media[] array and individual image fields</li>
            <li><strong>Fix 2 - Merge Strategy:</strong> Combines images from media[] array AND individual image fields (image, small_image, thumbnail) for maximum coverage</li>
            <li><strong>Fix 3 - Better Logging:</strong> Shows success and failure counts, making it easier to diagnose issues</li>
            <li><strong>Fix 4 - Error Messages:</strong> Provides clearer error messages when image downloads fail</li>
        </ul>

        <h3>Expected Results:</h3>
        <ul>
            <li>More images per product (combining all sources)</li>
            <li>Fewer or no duplicate images in galleries</li>
            <li>Better error messages in logs if images fail to download</li>
            <li>Products should have both featured image AND gallery images</li>
        </ul>
    </div>
</div>";

echo "<div style='text-align: center; margin-top: 40px; color: #666;'>
    <p>Test Image Migration Fixes - Magento to WordPress Migrator</p>
</div>

</body>
</html>";
