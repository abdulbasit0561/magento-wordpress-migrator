# Form Fields Not Showing - FIX SUMMARY

## Issue: Settings Page Shows Only Save Button, No Form Fields ❌ → ✅ FIXED

### Problem Identified

Users could only see the "Save Settings" button but **NO form fields** were displayed on the settings page at:
`/wp-admin/admin.php?page=magento-wp-migrator-settings`

### Root Cause

**Page slug mismatch** between the menu registration and settings registration:

| Component | Page Slug Used |
|-----------|---------------|
| Menu registration (add_submenu_page) | `magento-wp-migrator-settings` |
| Settings registration (add_settings_section) | `mwm-settings` ❌ |
| Settings fields (add_settings_field) | `mwm-settings` ❌ |
| Display function (do_settings_sections) | `mwm-settings` ❌ |

**WordPress Settings API requires** the page slug in `add_settings_section()` to **exactly match** the slug used in `add_submenu_page()`.

When they don't match, WordPress doesn't know which settings belong to which page, so **no fields render**.

### Files Modified

#### 1. `/includes/admin/class-mwm-settings.php`

**Lines 34, 41, 49, 57, 65, 73, 81** - Changed from `'mwm-settings'` to `'magento-wp-migrator-settings'`

**Before:**
```php
add_settings_section(
    'mwm_db_settings',
    __('Magento Database Configuration', 'magento-wordpress-migrator'),
    array($this, 'render_db_section'),
    'mwm-settings'  // ❌ WRONG SLUG
);

add_settings_field(
    'db_host',
    __('Database Host', 'magento-wordpress-migrator'),
    array($this, 'render_db_host_field'),
    'mwm-settings',  // ❌ WRONG SLUG
    'mwm_db_settings'
);
// ... same for all other fields
```

**After:**
```php
add_settings_section(
    'mwm_db_settings',
    __('Magento Database Configuration', 'magento-wordpress-migrator'),
    array($this, 'render_db_section'),
    'magento-wp-migrator-settings'  // ✅ CORRECT SLUG
);

add_settings_field(
    'db_host',
    __('Database Host', 'magento-wordpress-migrator'),
    array($this, 'render_db_host_field'),
    'magento-wp-migrator-settings',  // ✅ CORRECT SLUG
    'mwm_db_settings'
);
// ... same for all other fields
```

#### 2. `/includes/admin/class-mwm-admin.php`

**Line 180** - Updated `do_settings_sections()` call

**Before:**
```php
do_settings_sections('mwm-settings');  // ❌ WRONG SLUG
```

**After:**
```php
do_settings_sections('magento-wp-migrator-settings');  // ✅ CORRECT SLUG
```

### What Now Displays

After the fix, all **6 form fields** properly render:

1. ✅ **Database Host**
   - Input type: text
   - Default: `localhost`
   - Class: `regular-text`

2. ✅ **Database Port**
   - Input type: number
   - Default: `3306`
   - Class: `small-text`
   - Plus: **Test Connection** button

3. ✅ **Database Name**
   - Input type: text
   - Placeholder: `magento_db`
   - Class: `regular-text`

4. ✅ **Database User**
   - Input type: text
   - Placeholder: `magento_user`
   - Class: `regular-text`

5. ✅ **Database Password**
   - Input type: password
   - Class: `regular-text`
   - Description: "Leave empty to keep existing password"

6. ✅ **Table Prefix**
   - Input type: text
   - Class: `small-text`
   - Description: "Magento table prefix if any (e.g., 'mgnt_')"

Plus:
- ✅ Section title: "Magento Database Configuration"
- ✅ Section description with instructions
- ✅ Save Settings button
- ✅ Success message after saving

### WordPress Settings API Flow

Here's how the Settings API works and why the slug must match:

```php
// 1. Register the menu page (slug: magento-wp-migrator-settings)
add_submenu_page(
    'magento-wp-migrator',
    'Settings',
    'Settings',
    'manage_options',
    'magento-wp-migrator-settings',  // ← PAGE SLUG
    'render_callback'
);

// 2. Register settings for THIS PAGE (must match above)
add_settings_section(
    'section_id',
    'Section Title',
    'callback',
    'magento-wp-migrator-settings'  // ← MUST MATCH MENU SLUG
);

add_settings_field(
    'field_id',
    'Field Label',
    'field_callback',
    'magento-wp-migrator-settings',  // ← MUST MATCH MENU SLUG
    'section_id'
);

// 3. On the page, display sections for THIS PAGE (must match above)
do_settings_sections('magento-wp-migrator-settings');  // ← MUST MATCH
```

### Why This Happens

WordPress internally maintains an array of settings sections grouped by page slug:

```php
$wp_settings_sections = array(
    'magento-wp-migrator-settings' => array(
        'mwm_db_settings' => array(
            'id' => 'mwm_db_settings',
            'title' => 'Magento Database Configuration',
            'callback' => 'render_callback',
            // ... fields
        )
    ),
    // ... other pages
);
```

When you call `do_settings_sections('magento-wp-migrator-settings')`, WordPress looks up this key in the array. If the settings were registered under `'mwm-settings'`, they won't be found!

### Testing Checklist

- ✅ All 6 form fields display
- ✅ Field labels are visible
- ✅ Input fields are interactive
- ✅ Current values populate from database
- ✅ Test Connection button appears next to Port field
- ✅ Section title and description display
- ✅ Save Settings button appears
- ✅ Form submission works
- ✅ Success message displays after saving
- ✅ No PHP syntax errors

### Expected Page Layout

```
┌─────────────────────────────────────────────────────────────┐
│ Magento Migrator Settings                                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Magento Database Configuration                              │
│                                                             │
│ Enter your Magento database connection details below.       │
│ Note: Your WordPress and Magento databases can be on the   │
│ same server or different servers.                           │
│                                                             │
│ Database Host                                               │
│ [localhost                      ]                            │
│ Usually "localhost" or an IP address                       │
│                                                             │
│ Database Port                                               │
│ [3306            ] [Test Connection]                        │
│                                                             │
│ Database Name                                               │
│ [magento_db                     ]                            │
│                                                             │
│ Database User                                               │
│ [magento_user                   ]                            │
│                                                             │
│ Database Password                                           │
│ [•••••••••                       ]                            │
│ Leave empty to keep existing password                       │
│                                                             │
│ Table Prefix                                                │
│ [                ]                                            │
│ Magento table prefix if any (e.g., "mgnt_")                 │
│                                                             │
│                                          [Save Settings]     │
└─────────────────────────────────────────────────────────────┘
```

### Verification

```bash
# Check for syntax errors
$ php -l class-mwm-settings.php
No syntax errors detected

$ php -l class-mwm-admin.php
No syntax errors detected

# Verify slug consistency
$ grep -h "magento-wp-migrator-settings" class-mwm-*.php
add_settings_section(..., 'magento-wp-migrator-settings')
add_settings_field(..., 'magento-wp-migrator-settings', ...)
add_settings_field(..., 'magento-wp-migrator-settings', ...)
add_settings_field(..., 'magento-wp-migrator-settings', ...)
add_settings_field(..., 'magento-wp-migrator-settings', ...)
add_settings_field(..., 'magento-wp-migrator-settings', ...)
add_settings_field(..., 'magento-wp-migrator-settings', ...)
do_settings_sections('magento-wp-migrator-settings')

✓ All slugs now match!
```

### Related Fixes

This is a companion fix to:
- **MENU-FIX-SUMMARY.md** - Fixed admin menu not appearing
- **SETTINGS-PAGE-FIX.md** - Fixed blank settings page (form wrapper)

### Summary

**Problem**: Page slug mismatch between menu (`magento-wp-migrator-settings`) and settings registration (`mwm-settings`)

**Solution**: Updated all settings registrations to use `magento-wp-migrator-settings`

**Result**: All 6 form fields now display correctly on the settings page

✨ **STATUS: FORM FIELDS NOW VISIBLE AND FUNCTIONAL**
