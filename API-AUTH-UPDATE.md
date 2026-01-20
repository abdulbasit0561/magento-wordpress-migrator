# Magento REST API Authentication - Complete Update

## Overview

The plugin has been updated from **database-based connection** to **Magento REST API authentication**. This is the proper way to connect to Magento for data migration.

---

## What Changed

### BEFORE: Database Connection Only
- Database Host
- Database Port
- Database Name
- Database User
- Database Password
- Table Prefix

### AFTER: REST API Authentication (Primary)
- **Magento Store URL** - Your Magento store URL
- **API Version** - V1 or V2 dropdown
- **Consumer Key** - OAuth Consumer Key
- **Consumer Secret** - OAuth Consumer Secret
- **Access Token** - OAuth Access Token
- **Access Token Secret** - OAuth Access Token Secret
- **Test API Connection** - Test button

PLUS optional database connection for advanced use cases.

---

## New Settings Page Layout

### Section 1: Magento REST API Configuration

```
┌─────────────────────────────────────────────────────────────┐
│ Magento REST API Configuration                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Enter your Magento REST API credentials below. These are   │
│ required to connect to your Magento store.                 │
│                                                             │
│ You can create API credentials in Magento Admin →          │
│ System → Integrations → Add New Integration.               │
│                                                             │
│ Required Permissions: Products, Categories, Customers,     │
│ Orders (Read/Write)                                        │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ Magento Store URL                                          │
│ [https://yourstore.com            ]                        │
│ Full URL of your Magento store                             │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ API Version                                                 │
│ [V1 ▼]                                                      │
│ Magento API version (usually V1 or V2)                     │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ Consumer Key                                                │
│ [your_consumer_key               ]                        │
│ Consumer Key from Magento integration                      │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ Consumer Secret                                             │
│ [•••••••••                        ]                        │
│ Consumer Secret from Magento integration                    │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ Access Token                                                │
│ [your_access_token               ]                        │
│ Access Token from Magento integration                      │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ Access Token Secret                                         │
│ [•••••••••                        ]  [Test API Connection]    │
│ Access Token Secret from Magento integration                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Section 2: Advanced - Direct Database Access (Optional)

```
┌─────────────────────────────────────────────────────────────┐
│ Advanced: Direct Database Access                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Optionally connect directly to Magento database for        │
│ advanced use cases.                                         │
│                                                             │
│ This requires direct database access and is not            │
│ recommended for most users. Use the REST API connection    │
│ above.                                                     │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ ☐ Use direct database connection instead of REST API       │
│ Enable this only if you need direct database access.        │
│ REST API is recommended.                                    │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ Database Host                                               │
│ [localhost                      ]                            │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ Database Name                                               │
│ [magento_db                     ]                            │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ Database User                                               │
│ [magento_user                   ]                            │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ Database Password                                           │
│ [•••••••••                       ]                            │
│ Leave empty to keep existing password                       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Files Modified

### 1. `/includes/admin/class-mwm-settings.php`

**Changed:**
- Updated `register_settings()` to register API fields (lines 27-133)
- Updated `sanitize_settings()` to handle API credentials (lines 141-160)
- Added `render_api_section()` - API section description (lines 162-169)
- Added `render_store_url_field()` - Store URL field (lines 171-179)
- Added `render_api_version_field()` - API version dropdown (lines 181-192)
- Added `render_consumer_key_field()` - Consumer key field (lines 194-202)
- Added `render_consumer_secret_field()` - Consumer secret field (lines 204-212)
- Added `render_access_token_field()` - Access token field (lines 214-222)
- Added `render_access_token_secret_field()` - Access token secret field (lines 224-239)
- Added `render_use_database_field()` - Use database checkbox (lines 251-260)
- Updated `render_db_section()` - Changed to advanced section (lines 242-247)
- Updated `save_settings()` - Preserve API secrets (lines 313-340)

**Removed:**
- `db_port` field (no longer needed)
- `table_prefix` field (no longer needed)

---

## How to Create Magento Integration

### Step 1: Create Integration in Magento

1. Log in to Magento Admin
2. Go to **System** → **Extensions** → **Integrations**
3. Click **Add New Integration**
4. Fill in the details:
   - **Name**: "WordPress Migration"
   - **Callback URL**: `https://your-wordpress-site.com` (can be any URL)
   - **Identity Link URL**: `https://your-wordpress-site.com`
   - **Email**: Your email
5. Under **API**, select these permissions:
   - ✅ **Products** → Read/Write
   - ✅ **Categories** → Read/Write
   - ✅ **Customers** → Read/Write
   - ✅ **Orders** → Read/Write
6. Click **Save**

### Step 2: Get Credentials

After saving, Magento will show:
- **Consumer Key**
- **Consumer Secret**
- **Access Token**
- **Access Token Secret**

Copy these four values to the WordPress plugin settings page.

---

## Connection Types

### Primary: REST API (Recommended)

**Pros:**
- ✅ Official Magento API
- ✅ Secure OAuth authentication
- ✅ No database access required
- ✅ Works with remote Magento stores
- ✅ Magento best practice
- ✅ Supports all Magento versions

**Cons:**
- ⚠️ Slower than direct database
- ⚠️ Requires API integration setup

### Advanced: Direct Database

**Pros:**
- ✅ Fast data transfer
- ✅ No API setup needed

**Cons:**
- ❌ Requires database access
- ❌ Security risk
- ❌ Magento version dependent
- ❌ Not recommended by Magento

---

## Field Descriptions

### API Credentials

| Field | Description | Example | Required |
|-------|-------------|---------|----------|
| **Store URL** | Full Magento store URL | `https://mystore.com` | Yes |
| **API Version** | Magento API version | `V1` or `V2` | Yes |
| **Consumer Key** | OAuth Consumer Key | `05b3a2...` | Yes |
| **Consumer Secret** | OAuth Consumer Secret | `6a8f9...` | Yes |
| **Access Token** | OAuth Access Token | `9c2d1...` | Yes |
| **Access Token Secret** | OAuth Token Secret | `7e5b8...` | Yes |

### Database (Optional)

| Field | Description | Example | Required |
|-------|-------------|---------|----------|
| **Database Host** | MySQL server hostname | `localhost` | No |
| **Database Name** | Magento database name | `magento2` | No |
| **Database User** | MySQL username | `magento_user` | No |
| **Database Password** | MySQL password | `••••••` | No |

---

## Security Features

1. **Passwords Preserved**: Secrets are not overwritten if left empty
2. **Sanitization**: All text fields are sanitized
3. **URL Validation**: Store URL is validated as proper URL format
4. **Nonce Verification**: All forms protected with WordPress nonces
5. **Capability Check**: Only administrators can access settings

---

## Next Steps

The settings page now shows all required API fields. Future work needed:

1. ✅ Settings form updated - **COMPLETE**
2. ⏳ Create Magento REST API connector class
3. ⏳ Update connection test to use REST API
4. ⏳ Update migrators to fetch data via API instead of database

---

## Testing

To verify the settings page works:

1. Visit `/wp-admin/admin.php?page=magento-wp-migrator-settings`
2. Verify all API fields display
3. Verify Test Connection button is visible
4. Verify Save Settings button works
5. Verify saved values persist

---

## Summary

The plugin now properly requests **Magento REST API credentials** instead of database connection details. This is the correct approach for integrating with Magento and follows Magento's recommended integration pattern.

Users can create an integration in Magento Admin, get OAuth credentials, and enter them in the WordPress plugin to authenticate and migrate data.

✨ **STATUS: SETTINGS PAGE UPDATED WITH MAGENTO REST API FIELDS**
