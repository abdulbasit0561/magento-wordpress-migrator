# Settings Page Field Reference

## Database Connection Fields (Implemented)

The Magento to WordPress Migrator plugin uses a **direct database connection** approach, not API-based connection. Here are the fields on the settings page:

### 1. Database Host
- **Field Name**: `mwm_settings[db_host]`
- **Default**: `localhost`
- **Description**: Usually "localhost" or an IP address
- **Example Values**:
  - `localhost` (if WordPress and Magento on same server)
  - `192.168.1.100` (Magento server IP)
  - `db.example.com` (remote database hostname)

### 2. Database Port
- **Field Name**: `mwm_settings[db_port]`
- **Default**: `3306`
- **Description**: MySQL port number
- **Example Values**:
  - `3306` (default MySQL port)
  - `3307` (if MySQL uses custom port)

### 3. Database Name
- **Field Name**: `mwm_settings[db_name]`
- **Default**: (empty)
- **Description**: Name of the Magento database
- **Example Values**:
  - `magento_db`
  - `magento2`
  - `magento_production`

### 4. Database User
- **Field Name**: `mwm_settings[db_user]`
- **Default**: (empty)
- **Description**: MySQL username with access to Magento database
- **Example Values**:
  - `magento_user`
  - `root` (development only)
  - `magento_db_user`

### 5. Database Password
- **Field Name**: `mwm_settings[db_password]`
- **Default**: (empty)
- **Description**: MySQL password for the database user
- **Note**: Leave empty to keep existing password when updating settings

### 6. Table Prefix
- **Field Name**: `mwm_settings[table_prefix]`
- **Default**: (empty)
- **Description**: Magento table prefix if any
- **Example Values**:
  - (empty) - no prefix
  - `mgnt_` - Magento 1 prefix
  - `magento2_` - custom prefix

## How to Find These Values

### Option 1: Check Magento's app/etc/env.php
```php
<?php
return [
    'db' => [
        'table_prefix' => 'mgnt_',
        'connection' => [
            'default' => [
                'host' => 'localhost',
                'dbname' => 'magento_db',
                'username' => 'magento_user',
                'password' => 'your_password',
                'port' => '3306',
            ],
        ],
    ],
];
```

### Option 2: Check Magento's local.xml (Magento 1.x)
```xml
<config>
    <global>
        <resources>
            <default_setup>
                <connection>
                    <host><![CDATA[localhost]]></host>
                    <username><![CDATA[magento_user]]></username>
                    <password><![CDATA[your_password]]></password>
                    <dbname><![CDATA[magento_db]]></dbname>
                    <active>1</active>
                </connection>
            </default_setup>
        </resources>
        <db>
            <table_prefix><![CDATA[mgnt_]]></table_prefix>
        </db>
    </global>
</config>
```

### Option 3: Ask Your Hosting Provider
If you're using managed hosting, contact support and request:
- Database hostname
- Database port (usually 3306)
- Database name
- Database username
- Database password
- Table prefix (if any)

## Test Connection Button

The "Test Connection" button appears next to the Port field and:

1. ✅ Tests database connectivity
2. ✅ Validates credentials
3. ✅ Detects Magento version (1.x or 2.x)
4. ✅ Shows success/error message inline

## Important Notes

### Security
- **Never** use `root` database user in production
- Create a dedicated MySQL user with only SELECT permissions
- Store credentials securely (WordPress options table is encrypted in modern WP)

### Privileges Required
The MySQL user needs these permissions on the Magento database:
- `SELECT` - Required for reading data
- `SHOW DATABASES` - For connection testing

### WordPress vs Magento Databases
The plugin connects to TWO databases:
- **WordPress Database** - Already connected (stores plugin settings, migrated data)
- **Magento Database** - Configured on settings page (source data to migrate)

These can be:
- Same database (different table prefixes)
- Different databases on same server
- Different databases on different servers

## What This Plugin Does NOT Use

This plugin does **not** use:
- ❌ API URL (not API-based)
- ❌ API Key/Token (uses direct database access)
- ❌ API Secret (uses direct database access)

The direct database approach is:
- ✅ Faster (no API overhead)
- ✅ More reliable (no API rate limits)
- ✅ Complete data access (access to all tables)
- ✅ Works offline (no external API calls)

## Troubleshooting

### "Connection Failed" Error

1. **Check firewall**: Ensure WordPress server can connect to MySQL server
2. **Verify credentials**: Test with MySQL client: `mysql -h host -P port -u user -p`
3. **Check MySQL user permissions**: Ensure user has access from WordPress server IP
4. **Verify database exists**: User can connect but database doesn't exist
5. **Check MySQL port**: Verify port is correct (default 3306)

### "Access Denied" Error

1. Username or password is incorrect
2. User doesn't have permission from WordPress server IP
3. User doesn't have SELECT permissions on Magento database
4. Database doesn't exist

### Success But No Data Found

1. Table prefix is incorrect
2. Connected to wrong database
3. Database is empty (fresh Magento install)
4. Tables exist but no data yet

## Example Configuration

### Local Development (Both on Same Server)
```
Database Host: localhost
Database Port: 3306
Database Name: magento2
Database User: root
Database Password: (dev password)
Table Prefix: (empty)
```

### Production (Separate Database Server)
```
Database Host: db1.example.com
Database Port: 3306
Database Name: magento_production
Database User: wp_magento_user
Database Password: (secure password)
Table Prefix: mgnt_
```

### Shared Hosting (cPanel)
```
Database Host: localhost
Database Port: 3306
Database Name: username_magento
Database User: username_magento
Database Password: (cPanel generated)
Table Prefix: (empty)
```
