# Magento Connector Setup Guide

## Overview

The Magento Connector is the **easiest way** to migrate data from Magento to WordPress/WooCommerce. Instead of configuring complex API credentials or database access, you simply upload one file to your Magento installation.

---

## How It Works

```
WordPress Plugin              Magento Store
     |                              |
     |    1. Upload connector       |
     |    ----------------->        |
     |          magento-connector.php
     |                              |
     |    2. Generate API key       |
     |    <-----------------        |
     |        Generated Key         |
     |                              |
     |    3. Connect & migrate      |
     |    <---------------->         |
     |    Products & Categories     |
```

**Benefits:**
- ✅ **No OAuth setup** - Bypass complex API integration
- ✅ **No database credentials** - No need for DB access
- ✅ **Secure** - API key authentication
- ✅ **Simple** - One file upload
- ✅ **Fast** - Direct access to Magento data

---

## Installation

### Step 1: Upload Connector to Magento

1. Locate the `magento-connector.php` file in the WordPress plugin:
   ```
   /wp-content/plugins/magento-wordpress-migrator/magento-connector.php
   ```

2. Upload this file to your **Magento root directory**:
   ```
   /path/to/magento/magento-connector.php
   ```

   Methods to upload:
   - **FTP/SFTP** - Use FileZilla or similar
   - **SSH** - `scp magento-connector.php user@server:/path/to/magento/`
   - **cPanel File Manager** - Upload via hosting control panel
   - **WP-CLI** (if WordPress and Magento on same server)

3. Verify the file is accessible:
   ```
   https://your-magento-site.com/magento-connector.php
   ```

---

### Step 2: Generate API Key

1. Visit the connector setup URL:
   ```
   https://your-magento-site.com/magento-connector.php?generate_key
   ```

2. You'll see a setup page with:
   - ✅ Success message
   - 🔑 **Generated API Key** (copy this!)
   - 📋 Setup instructions

3. **Copy the API Key** - You'll need it for WordPress configuration

4. **Security Note:**
   - The configuration file is created at: `connector-config.php`
   - Keep your API key secure
   - Don't share it publicly
   - You can delete `?generate_key` after setup

---

### Step 3: Configure WordPress Plugin

1. Go to **WordPress Admin** → **Magento** → **Migrator** → **Settings**

2. Set **Connection Mode** to: **Connector (Recommended)**

3. Fill in connector settings:

   **Connector URL:**
   ```
   https://your-magento-site.com/magento-connector.php
   ```

   **Connector API Key:**
   ```
   [Paste the API key from Step 2]
   ```

4. Click **Save Changes**

---

### Step 4: Test Connection

1. Click **Test Connector Connection** button

2. Expected results:
   ```
   ✅ Connection successful
   Magento Version: Magento 1
   ```

3. If connection fails:
   - Check connector URL is correct
   - Verify API key matches exactly
   - Ensure magento-connector.php is in Magento root
   - Check server error logs

---

## API Endpoints

The connector provides the following endpoints:

### Test Connection
```
GET /magento-connector.php?endpoint=test
```

### Get Products
```
GET /magento-connector.php?endpoint=products&limit=100&page=1
```

### Get Single Product
```
GET /magento-connector.php?endpoint=product&sku=PRODUCT-SKU
```

### Get Product Count
```
GET /magento-connector.php?endpoint=products_count
```

### Get Categories
```
GET /magento-connector.php?endpoint=categories
```

### Get Category
```
GET /magento-connector.php?endpoint=category&id=123
```

### Get Category Count
```
GET /magento-connector.php?endpoint=categories_count
```

---

## Security Features

### API Key Authentication

Every request requires authentication:

```php
// Via header
X-Magento-Connector-Key: your-api-key-here

// Or via parameter
?api_key=your-api-key-here
```

### Access Logging

All connector access is logged to:
```
/var/log/connector-access.log
```

Log format:
```
[2024-01-15 10:30:45] [SUCCESS] 192.168.1.1 - Authenticated - /magento-connector.php?endpoint=products
[2024-01-15 10:31:12] [FAILED] 192.168.1.2 - Invalid API key - /magento-connector.php?endpoint=products
```

### Error Logging

PHP errors logged to:
```
/var/log/connector-errors.log
```

---

## File Structure

After setup, you'll have:

```
/path/to/magento/
├── magento-connector.php          # Main connector file
├── connector-config.php            # Generated config (API key)
└── var/
    └── log/
        ├── connector-access.log    # Access logs
        └── connector-errors.log    # Error logs
```

---

## Troubleshooting

### Issue: "Unable to connect to Magento"

**Solutions:**
1. Check connector URL is correct
2. Verify magento-connector.php is in Magento root
3. Test connector URL in browser:
   ```
   https://your-magento-site.com/magento-connector.php?endpoint=test
   ```
4. Check server firewall isn't blocking requests
5. Verify SSL certificate is valid (if using HTTPS)

### Issue: "Invalid API Key"

**Solutions:**
1. Regenerate API key:
   ```
   https://your-magento-site.com/magento-connector.php?generate_key
   ```
2. Copy API key exactly (no extra spaces)
3. Check connector-config.php exists in Magento root
4. Clear WordPress cache and try again

### Issue: "Magento installation not found"

**Solutions:**
1. Ensure magento-connector.php is in Magento root directory
2. Verify file permissions (644)
3. Check Magento is properly installed
4. Ensure PHP version is compatible

### Issue: "Permission denied" or "403 Forbidden"

**Solutions:**
1. Check file permissions:
   ```bash
   chmod 644 magento-connector.php
   ```
2. Verify .htaccess isn't blocking access
3. Check server security settings
4. Ensure web server can read the file

### Issue: "Migration is slow"

**Solutions:**
1. Increase PHP memory limit in Magento's php.ini
2. Optimize Magento indexes
3. Use smaller batch sizes
4. Check server resources

---

## Comparison: Connector vs API vs Database

| Feature | Connector | REST API | Database |
|---------|-----------|----------|----------|
| **Ease of Setup** | ⭐⭐⭐⭐⭐ Easiest | ⭐⭐⭐ Moderate | ⭐⭐ Complex |
| **Time to Setup** | 5 minutes | 30+ minutes | 20+ minutes |
| **OAuth Required** | ❌ No | ✅ Yes | ❌ No |
| **DB Credentials** | ❌ No | ❌ No | ✅ Yes |
| **Magento Access** | ✅ Direct | ✅ Via API | ✅ Direct |
| **Speed** | ⭐⭐⭐⭐ Fast | ⭐⭐⭐ Medium | ⭐⭐⭐⭐⭐ Fastest |
| **Security** | ⭐⭐⭐⭐ API Key | ⭐⭐⭐⭐⭐ OAuth | ⭐⭐⭐ DB creds |
| **Firewall Friendly** | ✅ Yes | ⚠️ Sometimes | ❌ No |
| **Recommended For** | **Everyone** | Advanced users | DB admins |

---

## Advanced Usage

### Custom Batch Size

Adjust batch size for performance:

```php
// In connector request
$magento-connector.php?endpoint=products&limit=50&page=1
```

WordPress plugin default: 20 products per batch

### Filtering Categories

Get only top-level categories:
```
?endpoint=categories&parent_id=2
```

### Direct Integration

Use the connector in your own code:

```php
require_once 'connector-config.php';

// Use MAGENTO_CONNECTOR_KEY constant
$api_key = MAGENTO_CONNECTOR_KEY;
```

---

## Uninstalling

### To remove connector from Magento:

1. Delete the files:
   ```bash
   rm /path/to/magento/magento-connector.php
   rm /path/to/magento/connector-config.php
   ```

2. Optionally remove logs:
   ```bash
   rm /path/to/magento/var/log/connector-*.log
   ```

### To remove from WordPress:

1. Go to **Settings** → Change **Connection Mode** to another option
2. Click **Save Changes**

---

## Compatibility

### Magento Versions

| Magento Version | Support |
|----------------|---------|
| Magento 1.7 - 1.9 | ✅ Fully Supported |
| Magento 2.0 - 2.4 | ⚠️ Limited (see below) |

**Magento 2 Note:** Full Magento 2 support is coming soon. Currently, use database mode for M2.

### WordPress Versions

- WordPress 5.0+
- WooCommerce 3.0+

### PHP Versions

- PHP 7.0+
- PHP 8.0+ (recommended)

---

## Performance Tips

1. **Use during off-peak hours** - Run migrations when traffic is low
2. **Optimize Magento first** - Reindex and clean cache before migrating
3. **Increase memory limits** - Both WordPress and Magento
4. **Use HTTPS** - Faster and more secure
5. **Monitor logs** - Check var/log for errors

---

## FAQ

**Q: Is the connector safe to use?**
A: Yes. It uses API key authentication and logs all access. Only provides read-only access to products and categories.

**Q: Can I use the connector for multiple WordPress sites?**
A: Yes. Each WordPress site can connect to the same Magento connector using the same API key.

**Q: Does the connector modify Magento data?**
A: No. The connector only reads data from Magento. It doesn't write, modify, or delete anything.

**Q: Can I change the API key?**
A: Yes. Delete connector-config.php and visit ?generate_key again to create a new key.

**Q: What if my Magento is behind a firewall?**
A: The connector needs to be accessible from the WordPress server. Ensure port 443 (HTTPS) or 80 (HTTP) is open.

**Q: Does this work with Magento Cloud?**
A: Yes. Upload magento-connector.php to the Magento Cloud server.

**Q: Can I use this on localhost?**
A: Yes, but WordPress and Magento need to be able to communicate. Use ngrok or similar if needed.

---

## Support

For issues or questions:

1. Check this guide's Troubleshooting section
2. Review connector logs: `var/log/connector-*.log`
3. Check WordPress debug.log
4. Contact support with:
   - Magento version
   - WordPress version
   - Connector URL (anonymized)
   - Error messages
   - Relevant log entries

---

## Changelog

### Version 1.0.0 (2024-01-15)
- Initial release
- Support for Magento 1.x
- Product and category endpoints
- API key authentication
- Access and error logging
- Connection testing
- Batch fetching support

---

## License

This connector is part of the Magento to WordPress Migrator plugin.

Copyright © 2024

---

## Quick Reference Card

```
┌─────────────────────────────────────────────┐
│      MAGENTO CONNECTOR QUICK SETUP         │
├─────────────────────────────────────────────┤
│                                             │
│  1. Upload magento-connector.php           │
│     → Magento root directory               │
│                                             │
│  2. Generate API Key                       │
│     → ?generate_key                        │
│                                             │
│  3. Configure WordPress                    │
│     → Connection Mode: Connector           │
│     → Connector URL: [URL]                 │
│     → API Key: [paste key]                 │
│                                             │
│  4. Test Connection                        │
│     → Click "Test Connector Connection"    │
│                                             │
│  5. Start Migrating!                       │
│     → Products, Categories, etc.           │
│                                             │
└─────────────────────────────────────────────┘

CONNECTOR URL FORMAT:
https://your-magento.com/magento-connector.php

ENDPOINTS:
?endpoint=test
?endpoint=products&limit=100&page=1
?endpoint=categories
?endpoint=products_count
```
