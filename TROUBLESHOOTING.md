# Magento to WordPress Migrator - Menu Not Showing? Troubleshooting Guide

## Issue: Admin Menu Not Visible

If you've activated the plugin but don't see the "Magento Migrator" menu in the WordPress admin, follow these steps:

### 1. Verify Plugin Activation

1. Go to **Plugins** → **Installed Plugins**
2. Find "Magento to WordPress Migrator"
3. Confirm it shows "Active" in blue
4. If not active, click "Activate"

### 2. Check User Capabilities

The menu is only visible to users with the `manage_options` capability (typically Administrators).

1. Go to **Users** → **All Users**
2. Ensure you're logged in as an **Administrator**
3. If you're an Editor or other role, you won't see the menu

### 3. Clear Browser Cache

Sometimes cached CSS can hide menu items:

1. Clear your browser cache
2. Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)
3. Try a different browser or incognito mode

### 4. Check for Plugin Conflicts

Other plugins might be causing conflicts:

1. Deactivate all other plugins temporarily
2. Check if the menu appears
3. If it does, reactivate plugins one by one to find the conflict

### 5. Check for JavaScript Errors

Open your browser's developer console:

1. Press F12 or right-click → Inspect
2. Go to the Console tab
3. Look for any red error messages
4. Fix any JavaScript errors if found

### 6. Review WordPress Debug Log

Check for errors in the debug log:

1. Enable debug mode in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
2. Try loading the admin page
3. Check `wp-content/debug.log` for errors
4. Look for any fatal errors or warnings related to the plugin

### 7. Verify Plugin Files

Ensure all files are uploaded correctly:

1. Connect via FTP or file manager
2. Navigate to `wp-content/plugins/magento-wordpress-migrator/`
3. Verify these files exist:
   - `magento-wordpress-migrator.php` (main file)
   - `includes/class-mwm-db.php`
   - `includes/admin/class-mwm-admin.php`
   - `assets/css/admin.css`
   - `assets/js/admin.js`

### 8. Check PHP Version

The plugin requires PHP 7.4 or higher:

1. Go to **Tools** → **Site Health** → **Info**
2. Find "Server configuration"
3. Check "PHP Version"
4. Must be 7.4 or higher

### 9. Check WordPress Version

The plugin requires WordPress 5.8 or higher:

1. Go to **Dashboard** → **Updates**
2. Check "Current Version"
3. Must be 5.8 or higher

### 10. WooCommerce Warning

The plugin shows a warning if WooCommerce is not active, but the menu should still appear:

1. The menu will be visible even without WooCommerce
2. You'll see an admin notice about WooCommerce
3. Install WooCommerce for full functionality

### Quick Test

Add this to your theme's `functions.php` temporarily to test:

```php
add_action('admin_notices', function() {
    if (class_exists('Magento_WordPress_Migrator')) {
        echo '<div class="notice notice-success"><p>Plugin class loaded!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Plugin class NOT loaded</p></div>';
    }

    $menu_exists = menu_page_url('magento-wp-migrator', false);
    if ($menu_exists) {
        echo '<div class="notice notice-success"><p>Menu registered!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Menu NOT registered</p></div>';
    }
});
```

### Expected Menu Structure

When working correctly, you should see:

```
Magento Migrator
├── Dashboard (toplevel)
├── Settings
├── Migration
└── Logs
```

The menu should appear with a download icon (↓) at position 30 in the admin sidebar.

### Still Not Working?

1. **Reinstall the plugin:**
   - Deactivate and delete the plugin
   - Reupload all files
   - Reactivate

2. **Check .htaccess file:**
   - Ensure no rules are blocking the plugin

3. **Contact support:**
   - Include your WordPress version
   - Include your PHP version
   - Include any error messages from debug.log
   - List other active plugins

### Manual Database Check

You can verify the plugin is loaded by checking the options table:

```sql
SELECT option_name, option_value
FROM wp_options
WHERE option_name LIKE 'mwm_%';
```

You should see:
- `mwm_settings` - Plugin settings
- `mwm_migration_stats` - Migration statistics

## Success Indicators

If everything is working, you'll see:

1. ✅ "Magento Migrator" in the admin sidebar
2. ✅ Plugin active in Plugins list
3. ✅ No PHP errors in debug log
4. ✅ Dashboard page loads without errors
5. ✅ AJAX test connection works (once configured)

## Need Help?

Check the logs page within the plugin:
1. Go to **Magento Migrator** → **Logs**
2. Review any error messages
3. Use error details to diagnose issues
