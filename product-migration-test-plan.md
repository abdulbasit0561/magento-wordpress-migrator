# Product Migration Test Plan

## Pre-Migration Checklist

### 1. Database Connection Verification
- [ ] Go to WordPress Admin → Magento → Migrator → Settings
- [ ] Verify database credentials are filled in:
  - [ ] Database Host
  - [ ] Database Name
  - [ ] Database User
  - [ ] Database Password
  - [ ] Database Port (default: 3306)
  - [ ] Table Prefix (if applicable)

### 2. Test Database Connection
- [ ] Click "Test Connection" button
- [ ] Verify success message appears

### 3. Check Magento Database Access
Run this query in Magento database to verify product data exists:
```sql
-- Check product count
SELECT COUNT(*) FROM catalog_product_entity;

-- Check sample product
SELECT entity_id, sku, type_id, created_at
FROM catalog_product_entity
LIMIT 5;

-- Check price data exists
SELECT COUNT(*) FROM catalog_product_entity_decimal;

-- Check stock data exists
SELECT COUNT(*) FROM cataloginventory_stock_item;
```

## Migration Testing Steps

### Step 1: Backup WordPress Database
```bash
# Before testing, backup WordPress database
mysqldump -u wp_user -p wordpress_db > backup_before_product_migration.sql
```

### Step 2: Enable Debug Logging
Edit `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Step 3: Run Product Migration
1. Navigate to WordPress Admin → Magento → Migrator
2. Click "Migrate Products" button
3. Monitor progress in real-time
4. Wait for completion

### Step 4: Verify Results

#### Check Product Count
```php
// In WordPress admin, run in PHP console or add to page template
$products = wp_count_posts('product');
echo "Simple products: " . $products->simple . "\n";
echo "Variable products: " . $products->variable . "\n";
echo "Total published: " . $products->publish . "\n";
```

#### Check Product Data
Go to WooCommerce → Products and verify:
- [ ] Products have correct names
- [ ] Products have correct prices (not $0.00)
- [ ] Products have SKUs matching Magento
- [ ] Products have stock status set correctly
- [ ] Products have weight set (if applicable)
- [ ] Products have images

#### Check Specific Product Meta
```sql
-- Run in WordPress database
SELECT
    p.ID,
    p.post_title,
    pm_sku.meta_value AS sku,
    pm_price.meta_value AS price,
    pm_stock.meta_value AS stock,
    pm_weight.meta_value AS weight
FROM wp_posts p
LEFT JOIN wp_postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
LEFT JOIN wp_postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
LEFT JOIN wp_postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
LEFT JOIN wp_postmeta pm_weight ON p.ID = pm_weight.post_id AND pm_weight.meta_key = '_weight'
WHERE p.post_type = 'product'
LIMIT 10;
```

## Common Issues and Solutions

### Issue 1: All Products Have Price $0.00

**Symptom:**
Products are created but all show $0.00 as price

**Root Cause:**
The `get_product_attributes()` method only queries varchar table, price is in decimal table

**Solution:**
✅ FIXED - Now queries all EAV tables (varchar, int, decimal, text, datetime)

**Verify Fix:**
Check error log for: "MWM: Setting product meta for {ID} - Price: {actual_price}"

### Issue 2: Products Have No Stock Data

**Symptom:**
Products show "Out of stock" or have incorrect stock quantities

**Root Cause:**
Stock data wasn't being fetched from cataloginventory_stock_item table

**Solution:**
✅ FIXED - Added `get_product_stock()` method

**Verify Fix:**
```sql
SELECT
    p.post_title,
    pm_stock_status.meta_value AS stock_status,
    pm_manage_stock.meta_value AS manage_stock
FROM wp_posts p
LEFT JOIN wp_postmeta pm_stock_status ON p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status'
LEFT JOIN wp_postmeta pm_manage_stock ON p.ID = pm_manage_stock.post_id AND pm_manage_stock.meta_key = '_manage_stock'
WHERE p.post_type = 'product'
LIMIT 10;
```

### Issue 3: Migration Hangs or Times Out

**Symptom:**
Migration starts but progress doesn't update or page times out

**Possible Causes:**
1. Too many products to migrate at once
2. Server PHP max_execution_time too low
3. Server memory_limit too low
4. Large images taking too long to download

**Solutions:**
1. Reduce batch size in `class-mwm-migrator-products.php`:
   ```php
   private $batch_size = 10; // Reduce from 20
   ```

2. Increase PHP limits in `wp-config.php`:
   ```php
   ini_set('max_execution_time', 300);
   ini_set('memory_limit', '512M');
   ```

3. Disable image downloading temporarily to test:
   Comment out line 230 in `class-mwm-migrator-products.php`:
   ```php
   // $this->migrate_product_images($new_product_id, $full_product);
   ```

### Issue 4: Images Not Downloading

**Symptom:**
Products migrate but have no images

**Root Cause:**
- Media URL incorrect
- Images not accessible from WordPress server
- Image URLs in database are relative paths

**Solution:**
Check `class-mwm-db.php` method `get_media_url()`:
```php
public function get_media_url() {
    $base_url = $this->get_base_url();
    return $base_url . '/media/catalog/product';
}
```

**Verify:**
```sql
-- Check base_url in Magento core_config_data
SELECT value FROM core_config_data
WHERE path LIKE 'web/unsecure/base_url'
OR path LIKE 'web/secure/base_url';
```

### Issue 5: Categories Not Linked

**Symptom:**
Products migrate but aren't assigned to categories

**Root Cause:**
Category mapping failure - Magento category IDs don't match WooCommerce term IDs

**Solution:**
Categories must be migrated FIRST before products.

**Verify:**
```sql
-- Check if categories exist in WordPress
SELECT tm.name, tm.slug, tm.term_id
FROM wp_terms tm
INNER JOIN wp_term_taxonomy tt ON tm.term_id = tt.term_id
WHERE tt.taxonomy = 'product_cat';

-- Check if products have categories
SELECT
    p.post_title,
    GROUP_CONCAT(t.name SEPARATOR ', ') AS categories
FROM wp_posts p
LEFT JOIN wp_term_relationships tr ON p.ID = tr.object_id
LEFT JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
LEFT JOIN wp_terms t ON tt.term_id = t.term_id
WHERE p.post_type = 'product'
AND tt.taxonomy = 'product_cat'
GROUP BY p.ID
LIMIT 10;
```

## Performance Benchmarks

Expected migration times (on typical shared hosting):

| Product Count | Images | Estimated Time |
|--------------|--------|----------------|
| 100          | Yes    | 5-10 minutes   |
| 500          | Yes    | 30-45 minutes  |
| 1000         | Yes    | 1-2 hours      |
| 5000         | Yes    | 4-6 hours      |

**Without images:** Divide time by ~3

## Post-Migration Verification

### 1. Check Migration Logs
```bash
# View WordPress debug log
tail -f /workspace/wp-content/debug.log | grep "MWM:"
```

### 2. Verify Product Counts Match
```sql
-- Magento product count
SELECT COUNT(*) FROM magento_db.catalog_product_entity;

-- WordPress product count
SELECT COUNT(*) FROM wp_posts WHERE post_type = 'product';

-- Should match (or close if some failed)
```

### 3. Test Product in Storefront
- [ ] Visit a product page on the frontend
- [ ] Verify price displays correctly
- [ ] Verify add to cart works
- [ ] Verify images display
- [ ] Add product to cart and complete test purchase

### 4. Check WooCommerce Reports
- [ ] Go to WooCommerce → Reports
- [ ] Verify products appear in sales reports
- [ ] Check stock levels are accurate

## Rollback Procedure

If migration fails or produces incorrect results:

### 1. Delete Migrated Products
```sql
-- WARNING: This will delete all products
DELETE FROM wp_posts WHERE post_type = 'product';
DELETE FROM wp_postmeta WHERE post_id NOT IN (SELECT ID FROM wp_posts);
DELETE FROM wp_term_relationships WHERE object_id NOT IN (SELECT ID FROM wp_posts);
```

### 2. Restore from Backup
```bash
mysql -u wp_user -p wordpress_db < backup_before_product_migration.sql
```

### 3. Review Logs
Check debug logs for errors and fix issues before retrying

## Support

If issues persist after applying fixes:

1. Check WordPress debug log: `/wp-content/debug.log`
2. Check PHP error log: `/var/log/php-error.log`
3. Review all "MWM:" prefixed log entries
4. Verify Magento database tables exist and are accessible
5. Test database connection manually:
   ```php
   $conn = new mysqli('localhost', 'db_user', 'db_pass', 'db_name');
   if ($conn->connect_error) {
       die("Connection failed: " . $conn->connect_error);
   }
   echo "Connected successfully";
   ```
