# Product Image Migration Guide

## Overview

The Magento to WordPress Migrator now includes comprehensive image/media migration functionality. This guide explains how product images are fetched from Magento and assigned to WordPress/WooCommerce products.

## Features

### ✅ What's Included

1. **Complete Media Gallery Fetching**
   - Fetches ALL images from Magento product media gallery (not just base/small/thumbnail)
   - Supports both Magento 1.x and Magento 2.x
   - Includes image labels, positions, and disabled status

2. **Smart Image Processing**
   - Filters out disabled images
   - Sorts images by position (as configured in Magento)
   - Sets first image as featured image
   - Adds remaining images to product gallery

3. **Image Metadata**
   - Preserves image labels as alt text
   - Maintains image ordering from Magento
   - Stores all images in WordPress media library

4. **Error Handling**
   - Gracefully handles missing or invalid images
   - Logs all image upload attempts
   - Continues migration even if some images fail

## How It Works

### Architecture

```
Magento Store
    ↓
magento-connector.php (installed on Magento)
    ↓ (via API)
WordPress Plugin (MWM_Connector_Client)
    ↓
MWM_Migrator_Products
    ↓
WordPress Media Library & WooCommerce Products
```

### Data Flow

1. **Magento Side (magento-connector.php)**
   - `get_products_magento1()` - Fetches products with media gallery from Magento 1
   - `get_products_magento2()` - Fetches products with media gallery from Magento 2
   - Returns complete media array with all product images

2. **WordPress Side (class-mwm-migrator-products.php)**
   - `migrate_product_images()` - Processes media gallery and uploads images
   - `upload_image_from_url()` - Downloads and saves images to WordPress
   - Sets featured image and product gallery

### Image Data Structure

Media gallery items include:
```php
[
    'value' => '/p/r/product123.jpg',        // Relative path to image
    'file' => '/p/r/product123.jpg',         // Same as value
    'label' => 'Product Name - View 1',      // Image label (used as alt text)
    'position' => 1,                         // Sort order
    'media_type' => 'image',                 // Media type (image/video)
    'disabled' => false                      // Whether image is disabled
]
```

## Configuration

### Requirements

1. **Magento Side:**
   - `magento-connector.php` must be installed in Magento root
   - Connector must be configured with API key

2. **WordPress Side:**
   - WooCommerce installed and activated
   - Connector URL and API key configured in plugin settings
   - Uploads directory must be writable

3. **Server Requirements:**
   - PHP allow_url_fopen enabled or cURL enabled
   - Sufficient disk space for images
   - Adequate memory_limit and max_execution_time

### Settings

No special settings required. Images are migrated automatically during product migration.

**Image URL Construction:**
- Base URL: `{connector_url}/media/catalog/product/`
- Full URL: `{base_url}{image_path}`

Example:
- Connector URL: `https://mystore.com/magento-connector.php`
- Image Path: `/p/r/product123.jpg`
- Full URL: `https://mystore.com/media/catalog/product/p/r/product123.jpg`

## Usage

### During Product Migration

Images are migrated automatically when you run a product migration:

1. Go to **WordPress Admin** → **Magento** → **Migrator**
2. Click **"Migrate Products"**
3. Select migration options (all products or specific page)
4. Click **"Start Migration"**
5. Images will be downloaded and assigned automatically

### Testing Image Migration

A test script is included to verify image migration:

**Option 1: Browser Test**
1. Upload `test-image-migration.php` to WordPress root directory
2. Visit: `https://yoursite.com/test-image-migration.php`
3. Review test results

**Option 2: WP-CLI Test**
```bash
wp eval-file wp-content/plugins/magento-wordpress-migrator/test-image-migration.php
```

### What Gets Migrated

For each product, the migrator will:

1. ✅ Download the base image → Set as featured image
2. ✅ Download all additional gallery images → Add to product gallery
3. ✅ Set image labels as alt text for SEO
4. ✅ Maintain image order from Magento
5. ✅ Log all migration activity

## Troubleshooting

### Common Issues

#### 1. Images Not Appearing

**Symptoms:** Products are created but no images are attached

**Possible Causes:**
- Connector URL is incorrect
- Magento media directory is not accessible
- Image paths in database are incorrect

**Solutions:**
1. Verify connector settings
2. Test image URLs directly in browser
3. Check Magento media directory permissions

#### 2. Some Images Fail to Upload

**Symptoms:** Featured image works but gallery images are missing

**Possible Causes:**
- Network timeout during download
- Invalid image file
- Insufficient PHP memory

**Solutions:**
1. Check error log: `wp-content/debug.log`
2. Increase PHP memory_limit
3. Increase max_execution_time

#### 3. Images Have Wrong URLs

**Symptoms:** Images show broken link icon

**Possible Causes:**
- Wrong media URL base
- Magento using CDN
- Relative vs absolute path issues

**Solutions:**
1. Verify `$media_url` in `class-mwm-migrator-products.php`
2. Check if Magento uses CDN (update connector if needed)
3. Ensure image paths are relative (not full URLs)

### Debug Logging

Enable WordPress debug logging:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs:
```bash
tail -f wp-content/debug.log | grep "MWM"
```

### Magento-Specific Issues

#### Magento 1

If media gallery is empty:
```php
// In magento-connector.php, check:
$gallery = $product->getMediaGalleryImages();
```

#### Magento 2

If media gallery is empty:
```php
// In magento-connector.php, check:
$mediaGalleryEntries = $product->getMediaGalleryEntries();
```

### Manual Image URL Testing

Test if Magento images are accessible:

```bash
# Test base image URL
curl -I https://mystore.com/media/catalog/product/p/r/product123.jpg

# Expected: HTTP/1.1 200 OK
```

## Performance Optimization

### For Large Catalogs

1. **Batch Size:**
   - Default: 20 products per batch
   - Adjust in `class-mwm-migrator-products.php`: `private $batch_size = 20;`
   - Lower batch size if timing out

2. **Timeout Settings:**
   ```php
   // In class-mwm-migrator-products.php
   set_time_limit(1200); // 20 minutes
   ```

3. **Image Optimization:**
   - Optimize images in Magento before migration
   - Use WordPress image optimization plugins after migration

### Server Requirements

For 1000 products with ~5 images each:

- **Disk Space:** ~5-10 GB (depending on image sizes)
- **Memory:** 256M minimum, 512M recommended
- **Execution Time:** 30-60 minutes (depending on image sizes)

## Code Reference

### Modified Files

1. **magento-connector.php**
   - `get_products_magento1()` - Added media gallery fetching
   - `get_products_magento2()` - Added media gallery fetching

2. **includes/class-mwm-migrator-products.php**
   - `migrate_product_images()` - Enhanced to handle full media gallery
   - `upload_image_from_url()` - Added alt text support

### Key Functions

```php
// Fetch products with media
$products = $connector->get_products($limit, $page);

// Each product includes:
$product['media'] = [
    // All media gallery items
];

// Migrate images
$migrator->migrate_product_images($product_id, $product);
```

## Best Practices

### Before Migration

1. ✅ Backup WordPress database
2. ✅ Backup WordPress uploads directory
3. ✅ Test connector connection
4. ✅ Run test script to verify image access
5. ✅ Optimize images in Magento (optional)

### During Migration

1. ✅ Monitor progress in admin panel
2. ✅ Check error log if migration stops
3. ✅ Don't close browser until complete
4. ✅ Allow adequate time for large catalogs

### After Migration

1. ✅ Verify images appear on products
2. ✅ Check image alt text is set
3. ✅ Test product image gallery in storefront
4. ✅ Run image optimization plugin
5. ✅ Regenerate thumbnails if needed

## Advanced Usage

### Custom Image Processing

To add custom image processing, modify `upload_image_from_url()`:

```php
private function upload_image_from_url($image_url, $product_id, $media_item = array()) {
    // ... existing code ...

    // Custom: Add post meta for image source
    update_post_meta($id, '_magento_image_url', $image_url);

    // Custom: Add custom field
    if (!empty($media_item['custom_field'])) {
        update_post_meta($id, '_custom_field', $media_item['custom_field']);
    }

    return $id;
}
```

### Filtering Images

To filter which images get migrated:

```php
// In migrate_product_images(), after fetching media items
$media_items = array_filter($magento_product['media'], function($item) {
    // Skip images with "no-migrate" in label
    return strpos($item['label'], 'no-migrate') === false;
});
```

### CDN Support

If Magento uses a CDN, update media URL:

```php
// In __construct() of class-mwm-migrator-products.php
$cdn_url = 'https://cdn.mystore.com';
$this->media_url = $cdn_url . '/media/catalog/product';
```

## FAQ

**Q: Will this re-download existing images?**
A: No. WordPress `media_handle_sideload()` checks if image already exists by filename.

**Q: Can I migrate images separately from products?**
A: Not directly. Images are migrated as part of product migration.

**Q: What happens if an image download fails?**
A: The product is still created, but that particular image is skipped. Error is logged.

**Q: Are image sizes optimized?**
A: WordPress creates standard image sizes automatically. Use optimization plugin for compression.

**Q: Can I change which image is the featured image?**
A: Yes. The first image in the media gallery (position 1) becomes featured. Reorder in Magento to change.

**Q: Does this work with configurable/bundle products?**
A: Yes. Each product type (simple, configurable, bundle) will have its images migrated independently.

**Q: What about product videos?**
A: Currently only images are migrated. Videos in media gallery are skipped.

**Q: Can I migrate images from a staging Magento?**
A: Yes. Just configure the connector URL to point to staging site.

## Support

For issues or questions:

1. Check error logs first
2. Run test script to diagnose
3. Review troubleshooting section above
4. Check plugin documentation

## Changelog

### Version 1.1.0 (Current)
- ✅ Added complete media gallery fetching for Magento 1
- ✅ Added complete media gallery fetching for Magento 2
- ✅ Enhanced image migration with sorting and filtering
- ✅ Added image label to alt text conversion
- ✅ Improved error handling and logging
- ✅ Added comprehensive test script

---

**Last Updated:** 2025-01-17
**Plugin Version:** 1.1.0
