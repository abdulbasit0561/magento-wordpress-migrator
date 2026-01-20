# WooCommerce Compatibility Guide

## Overview

The Magento to WordPress Migrator plugin is now fully compatible with **WooCommerce 10.4.3** and later versions, including support for modern WooCommerce features like **High-Performance Order Storage (HPOS)**.

## Compatibility Features

### ✅ HPOS (High-Performance Order Storage)

The plugin fully supports HPOS, which moves order data from WordPress posts table to custom optimized tables.

**What was fixed:**
- Removed direct `update_post_meta()` calls for orders
- Replaced with `$order->update_meta_data()` CRUD methods
- Removed direct `$wpdb->update()` on posts table for dates
- Now uses `$order->set_date_created()` and `$order->set_date_modified()`
- Added proper HPOS compatibility declaration

**Code Example:**
```php
// OLD (not HPOS compatible):
update_post_meta($order->get_id(), '_magento_order_id', $order_id);

// NEW (HPOS compatible):
$order->update_meta_data('_magento_order_id', $order_id);
$order->save();
```

### ✅ WooCommerce Feature Declarations

The plugin declares compatibility with all major WooCommerce features:

- **Custom Order Tables (HPOS)** - Orders use optimized storage
- **Analytics** - Compatible with WC Analytics
- **Cart & Checkout Blocks** - Works with block-based checkout
- **Product Blocks** - Compatible with product blocks
- **Min/Max Quantity** - Compatible with quantity features

### ✅ Updated Version Requirements

**Plugin Header:**
```
WC requires at least: 8.0
WC tested up to: 10.4
```

**Minimum Requirements:**
- WordPress: 6.4+
- PHP: 7.4+
- WooCommerce: 8.0+

## Technical Changes

### 1. Plugin Header Updates

**File:** `magento-wordpress-migrator.php`

```php
/**
 * Plugin Name: Magento to WordPress Migrator
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 10.4
 * Woo: 9900200-9900299:9900400-9900499
 */
```

**What changed:**
- Updated version to 1.1.0
- Increased WordPress requirement to 6.4
- Increased WC requirement to 8.0
- Updated "WC tested up to" to 10.4
- Added "Woo" header for marketplace compatibility

### 2. HPOS Compatibility Declaration

**File:** `magento-wordpress-migrator.php`

```php
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        // ... other features
    }
});
```

**Why this matters:**
- Tells WooCommerce the plugin is HPOS compatible
- Removes warning in WordPress admin
- Ensures orders work with custom tables
- Future-proofs for when HPOS becomes default

### 3. Order Migration Updates

**File:** `includes/class-mwm-migrator-orders.php`

**Before (Incompatible):**
```php
// Store Magento order IDs
update_post_meta($order->get_id(), '_magento_order_id', $order_id);

// Directly update post date
global $wpdb;
$wpdb->update(
    $wpdb->posts,
    array('post_date' => $created_at->format('Y-m-d H:i:s')),
    array('ID' => $order->get_id())
);
```

**After (Compatible):**
```php
// Store Magento order IDs using CRUD
$order->update_meta_data('_magento_order_id', $order_id);
$order->update_meta_data('_magento_increment_id', $increment_id);

// Save with all changes including dates
$order->set_date_created($created_at);
$order->set_date_modified($updated_at);
$order->save();
```

**Benefits:**
- Works with both old (posts) and new (custom tables) storage
- No direct database manipulation
- Uses proper WooCommerce CRUD methods
- More maintainable and future-proof

### 4. Product Migration

Products still use `update_post_meta()` because:
- Products haven't moved to custom tables yet
- Only orders use HPOS currently
- `update_post_meta()` is still correct for products
- No changes needed in product migrator

## Testing Compatibility

### Automated Testing

Run the built-in test script:

```bash
# From WordPress root
php -r "
require_once 'wp-load.php';
if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    echo '✅ FeaturesUtil class exists - HPOS declarations will work\n';
} else {
    echo '❌ FeaturesUtil class not found\n';
}

// Check if HPOS is enabled
if (function_exists('wc_get_container')) {
    \$features = wc_get_container()->get(\Automattic\WooCommerce\Internal\Admin\Features::class);
    \$hpos_enabled = \$features->is_enabled('custom_order_tables');
    echo 'HPOS Enabled: ' . (\$hpos_enabled ? 'Yes' : 'No') . '\n';
}
"
```

### Manual Testing Checklist

- [ ] Plugin activates without warnings
- [ ] No "incompatible plugins" notice in WooCommerce > Settings > Advanced
- [ ] Product migration works correctly
- [ ] Order migration works correctly with HPOS enabled
- [ ] Order migration works correctly with HPOS disabled
- [ ] Migrated orders appear in WooCommerce > Orders
- [ ] Order meta data is preserved
- [ ] Order dates are correct
- [ ] Order items and addresses are correct

## Common Issues & Solutions

### Issue 1: "Incompatible Plugin" Warning

**Symptom:** Warning in WooCommerce settings about plugin incompatibility

**Cause:** Missing HPOS compatibility declaration

**Solution:** This is now fixed in version 1.1.0. The plugin declares HPOS compatibility.

**Verification:**
1. Go to WooCommerce > Status
2. Check "Compatibility" section
3. Should show: "High-Performance order storage: Compatible"

### Issue 2: Orders Not Saving

**Symptom:** Order migration fails or orders don't save

**Cause:** Direct post table manipulation with HPOS enabled

**Solution:** This is now fixed. Version 1.1.0 uses proper CRUD methods.

**Debug:**
```php
// Check if HPOS is enabled
add_action('admin_notices', function() {
    if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        $hpos_enabled = wc_get_container()
            ->get(\Automattic\WooCommerce\Internal\Admin\Features::class)
            ->is_enabled('custom_order_tables');

        echo '<div class="notice notice-info"><p>';
        echo 'HPOS Status: ' . ($hpos_enabled ? 'Enabled' : 'Disabled');
        echo '</p></div>';
    }
});
```

### Issue 3: Order Dates Not Preserved

**Symptom:** Migrated orders show current date instead of original Magento date

**Cause:** Direct post table update doesn't work with HPOS

**Solution:** Now uses `$order->set_date_created()` method in version 1.1.0

**Verification:**
```php
// After migration, check order date
$order = wc_get_order($order_id);
echo 'Created: ' . $order->get_date_created()->date('Y-m-d H:i:s');
```

## HPOS Migration Guide

If you're upgrading from an older version:

### Before Upgrade

1. **Backup your site:**
   ```bash
   wp db export backup-before-hpos-upgrade.sql
   ```

2. **Check current order storage:**
   - WooCommerce > Settings > Advanced > Custom order tables
   - Note current setting (enabled/disabled)

3. **Check existing migrated orders:**
   ```sql
   SELECT COUNT(*)
   FROM wp_postmeta
   WHERE meta_key = '_magento_order_id';
   ```

### Upgrade Process

1. **Update plugin to 1.1.0:**
   - WordPress admin → Plugins → Add New → Upload
   - Or replace files via FTP/SFTP

2. **Clear caches:**
   ```bash
   wp cache flush --allow-root
   ```

3. **Verify compatibility:**
   - Check WooCommerce > Status
   - Should show "Compatible" for all features

### After Upgrade

1. **Test order migration:**
   - Migrate a small batch of orders (10-20)
   - Verify they appear correctly
   - Check dates are preserved
   - Verify meta data exists

2. **Enable HPOS (if not already):**
   - WooCommerce > Settings > Advanced
   - Enable "Custom order tables"
   - Run synchronization

3. **Verify existing orders:**
   - All old migrated orders should still work
   - No data loss should occur

## Performance Considerations

### With HPOS Enabled

**Benefits:**
- Faster order queries
- Reduced database load
- Better scalability
- Automatic indexing

**Migration Impact:**
- No performance degradation
- Same or slightly better speed
- More reliable date handling

### Recommended Settings

For large Magento migrations:

```php
// In wp-config.php
define('MWM_BATCH_SIZE', 50); // Process 50 orders at a time
define('MWM_HPOS_COMPATIBLE', true); // Ensure HPOS mode

// Increase timeout for large migrations
set_time_limit(1800); // 30 minutes per batch
```

## Developer Notes

### Adding New Order Meta

When adding order meta data in customizations:

```php
// CORRECT (HPOS compatible)
$order = wc_get_order($order_id);
$order->update_meta_data('_my_custom_field', 'value');
$order->save();

// WRONG (not HPOS compatible)
update_post_meta($order_id, '_my_custom_field', 'value');
```

### Checking HPOS Status

```php
// Check if HPOS is enabled
function mwm_is_hpos_enabled() {
    if (!class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        return false;
    }

    $features = wc_get_container()
        ->get(\Automattic\WooCommerce\Internal\Admin\Features::class);

    return $features->is_enabled('custom_order_tables');
}

// Usage
if (mwm_is_hpos_enabled()) {
    // HPOS-specific code
} else {
    // Traditional post-based orders
}
```

### Future-Proofing Code

```php
// Always use WC CRUD methods
$order->set_status('completed');
$order->set_total($amount);
$order->update_meta_data($key, $value);
$order->save();

// Never use direct database manipulation
// Avoid: $wpdb->update(), $wpdb->insert(), etc.
```

## Version History

### Version 1.1.0 (2025-01-17)

- ✅ Added HPOS compatibility declaration
- ✅ Updated order migrator to use CRUD methods
- ✅ Removed direct post table manipulation
- ✅ Added compatibility with WC 10.4+
- ✅ Updated plugin header requirements
- ✅ Tested with WooCommerce 10.4.3

### Version 1.0.0

- Initial release
- Direct post meta and database manipulation
- Compatible with WooCommerce up to 8.0

## Support

For HPOS-related issues:

1. **Check logs:** `wp-content/debug.log`
2. **Verify HPOS status:** WooCommerce > Status
3. **Test with HPOS disabled** to isolate issue
4. **Report bugs** with HPOS status and WooCommerce version

## Resources

- [WooCommerce HPOS Documentation](https://woocommerce.com/document/high-performance-order-storage/)
- [WooCommerce CRUD Objects](https://developer.woocommerce.com/2023/01/03/the-crud-object-in-woocommerce/)
- [HPOS Compatibility Guide](https://developer.woocommerce.com/2023/01/18/how-to-declare-hpos-compatibility/)

---

**Last Updated:** 2025-01-17
**Plugin Version:** 1.1.0
**Tested with WooCommerce:** 10.4.3
