=== Magento to WordPress Migrator ===
Contributors: yourusername
Tags: magento, migration, woocommerce, import, export, e-commerce
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Migrate products, categories, customers, and orders from Magento to WordPress/WooCommerce with ease.

== Description ==

Magento to WordPress Migrator is a powerful plugin that allows you to seamlessly migrate your e-commerce data from Magento to WordPress/WooCommerce. Connect directly to your Magento database for fast and reliable migration.

= Features =

* Direct database connection to Magento (no API required)
* Migrate Products with all attributes, images, and variations
* Migrate Categories with hierarchy preserved
* Migrate Customers with addresses and passwords
* Migrate Orders with all order details
* Real-time progress tracking during migration
* Detailed error logging and reporting
* Batch processing for large datasets
* WooCommerce integration
* Supports Magento 1.x and 2.x database structures

= Use Cases =

* Switching from Magento to WordPress/WooCommerce
* Merging Magento store with existing WordPress site
* Creating a WordPress backup of Magento data
* A/B testing WordPress with Magento data

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/magento-wordpress-migrator` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to **Magento Migrator > Settings** to configure your Magento database connection
4. Test the connection to ensure it's working
5. Go to **Magento Migrator > Migration** to start migrating your data

== Frequently Asked Questions ==

= Does this plugin modify my Magento database? =

No, this plugin only reads from your Magento database. It does not modify or delete any data in Magento.

= What versions of Magento are supported? =

This plugin supports both Magento 1.x and Magento 2.x database structures.

= Can I migrate data incrementally? =

Yes, you can run migrations multiple times. The plugin will update existing records instead of creating duplicates.

= What happens to my images? =

Product images are automatically downloaded from Magento and imported into the WordPress media library.

= Are passwords migrated? =

Yes, customer passwords from Magento are migrated. Note that Magento passwords may need to be reset depending on the encryption method used.

= Is WooCommerce required? =

Yes, WooCommerce is required as this plugin creates WooCommerce products, orders, and customer data.

== Changelog ==

= 1.0.0 =
* Initial release
* Product migration with images and attributes
* Category migration with hierarchy
* Customer migration with addresses
* Order migration with line items
* Progress tracking and error logging
* Settings page with connection testing
