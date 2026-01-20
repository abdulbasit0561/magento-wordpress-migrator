# Comprehensive Debugging Guide

## How to Enable Debug Mode

To enable detailed debugging for the Magento to WordPress Migrator plugin:

### Step 1: Enable WordPress Debug Mode

Edit your `wp-config.php` file and add/set these constants:

```php
// Enable debug mode
define('WP_DEBUG', true);

// Log errors to /wp-content/debug.log
define('WP_DEBUG_LOG', true);

// Don't display errors on screen (security)
define('WP_DEBUG_DISPLAY', false);
```

### Step 2: Test Connection

1. Go to WordPress Admin > Magento Migrator > Settings
2. Fill in your API credentials
3. Click "Test API Connection"
4. Check `/wp-content/debug.log` for detailed logs

### Step 3: View Debug Log

The debug log is located at:
```
/path/to/wordpress/wp-content/debug.log
```

You can view it via:
- SSH/Terminal: `tail -f wp-content/debug.log`
- File Manager: Download the file
- cPanel: File Manager > wp-content > debug.log

## What Gets Logged

### 1. AJAX Handler (magento-wordpress-migrator.php)

**When:** Test Connection button clicked

**Logs:**
```
MWM DEBUG: Test Connection - Received credentials
MWM DEBUG: Store URL: https://example.com
MWM DEBUG: API Version: V1
MWM DEBUG: Consumer Key (partial): abc1234...xyz
MWM DEBUG: Access Token (partial): def5678...abc
MWM DEBUG: Creating MWM_API_Connector instance
MWM DEBUG: Calling test_connection()
MWM DEBUG: test_connection result: Array(...)
```

**What to Check:**
- Is Store URL correct?
- Is API Version what you expected?
- Are credentials set (not empty)?

### 2. API Connector Constructor (class-mwm-api-connector.php)

**Logs:**
```
MWM API Connector Constructor:
  Store URL: https://example.com
  API Version: V1
  Consumer Key: abc1234...xyz
  Consumer Secret Length: 32
  Access Token: def5678...abc
  Access Token Secret Length: 32
```

**What to Check:**
- Store URL has no trailing slash (should be trimmed)
- API Version is V1 or V2
- Consumer Secret and Access Token Secret have length (should be 28-32 chars typically)

### 3. Request Building (class-mwm-api-connector.php)

**Logs:**
```
MWM API Request: GET https://example.com/rest/default/V1/modules
MWM DEBUG: Endpoint: /modules
MWM DEBUG: Full URL: https://example.com/rest/default/V1/modules
MWM DEBUG: OAuth Parameters:
  oauth_consumer_key: abc123def456...
  oauth_token: 789xyz012abc...
  oauth_signature_method: HMAC-SHA256
  oauth_timestamp: 1736945678
  oauth_nonce: AbCdEf123456
  oauth_version: 1.0
  oauth_signature: YWJjZGVmZ2hpams...
MWM DEBUG: Final URL with OAuth params (first 200 chars): https://example.com/rest/default/V1/modules?oauth_consumer_key=abc...
```

**What to Check:**
- Full URL is correct format
- OAuth parameters all present
- oauth_timestamp is recent (within 5 minutes)
- oauth_signature is generated

### 4. OAuth Signature Generation (class-mwm-api-connector.php)

**Logs:**
```
MWM DEBUG: OAuth Timestamp: 1736945678
MWM DEBUG: OAuth Nonce: AbCdEf123456
MWM DEBUG: Sorted parameters for signature:
  oauth_consumer_key => abc123def456...
  oauth_nonce => AbCdEf123456
  oauth_signature_method => HMAC-SHA256
  oauth_timestamp => 1736945678
  oauth_token => 789xyz012abc...
  oauth_version => 1.0
MWM DEBUG: OAuth Base String (first 200 chars): GET&https%3A%2F%2Fexample.com%2Frest%2F...
MWM DEBUG: Signing Key (partial): abc123def456...&789xyz012abc...
MWM DEBUG: OAuth Signature (first 30 chars): YWJjZGVmZ2hpams...
```

**What to Check:**
- Timestamp is current Unix timestamp
- Nonce is random alphanumeric string
- Parameters are sorted alphabetically
- Base string format: `METHOD&URL&PARAMS`
- Signing key format: `consumer_secret&access_token_secret`
- Signature is base64-encoded

### 5. Response Handling (class-mwm-api-connector.php)

**Success Response:**
```
MWM API Response Code: 200
MWM API Response Body: {"items":[{"name":"Magento_Catalog","setup_version":"2.4.3"},...]}
MWM DEBUG: Response Headers: Array(...)
MWM DEBUG: Full Response Body: {"items":[...]}
```

**Error Response:**
```
MWM API Response Code: 403
MWM API Response Body: {"message":"The consumer isn't authorized to access %resources","errors":[]}
MWM API Error Data: Array
(
    [message] => The consumer isn't authorized to access %resources
    [errors] => Array()
)
MWM DEBUG: Error Parameters: (if available)
```

**What to Check:**
- Response code: 200 = success, 401 = auth failed, 403 = permissions, 404 = not found
- Response body: Is it valid JSON?
- Error message: What does Magento say is wrong?

## Common Issues and Solutions

### Issue 1: "The consumer isn't authorized to access %resources"

**Logs to Check:**
```
MWM API Request: GET https://example.com/rest/default/V1/modules
MWM API Response Code: 403
```

**Possible Causes:**

1. **OAuth Integration Not Activated in Magento**
   - Solution: Go to Magento Admin > System > Extensions > Integrations
   - Find your integration
   - Click "Activate"
   - Copy the new credentials

2. **Wrong Credentials**
   - Check: Consumer Key and Consumer Secret match Magento
   - Check: Access Token and Access Token Secret match Magento
   - Solution: Re-copy credentials from Magento integration

3. **Integration Revoked/Expired**
   - Solution: Reactivate integration in Magento

4. **Consumer Has No Permissions**
   - Solution: Edit integration in Magento
   - Set at least "Read" permissions for basic resources

### Issue 2: "Authentication failed (401)"

**Logs to Check:**
```
MWM DEBUG: Consumer Key (partial): abc123...
MWM DEBUG: Access Token (partial): xyz789...
MWM API Response Code: 401
```

**Possible Causes:**

1. **Wrong Consumer Key or Secret**
   - Check: Key matches exactly (no extra spaces)
   - Check: Secret is complete (not truncated)

2. **Wrong Access Token or Secret**
   - Check: Token matches exactly (no extra spaces)
   - Check: Secret is complete (not truncated)

3. **URL Encoding Issue**
   - Check: Credentials don't have special characters that got double-encoded
   - Solution: Re-enter credentials manually (don't copy-paste from PDF)

### Issue 3: "API endpoint not found (404)"

**Logs to Check:**
```
MWM DEBUG: Full URL: https://example.com/rest/default/V1/modules
MWM API Response Code: 404
```

**Possible Causes:**

1. **Wrong API Version Setting**
   - Check: API Version in settings
   - Try: Switch from V1 to V2 (or vice versa)
   - V1 = `/rest/default/V1/endpoint`
   - V2 = `/rest/V1/endpoint`

2. **Magento REST API Not Enabled**
   - Solution: Enable REST API in Magento
   - Check: System > Integrations > REST API Settings

3. **Wrong Store URL**
   - Check: URL format (https://example.com)
   - Check: Store is accessible in browser
   - Check: No trailing slash

### Issue 4: "Could not connect to Magento store"

**Logs to Check:**
```
MWM API Request Error: cURL error 7: couldn't connect to host
```

**Possible Causes:**

1. **Wrong Store URL**
   - Check: URL is correct
   - Check: URL includes https:// or http://
   - Check: Store is accessible from WordPress server

2. **Firewall Blocking Connection**
   - Solution: Allow outbound connections from WordPress server
   - Check: Magento server firewall allows connections

3. **DNS Resolution Issue**
   - Check: Domain resolves to correct IP
   - Check: WordPress server can resolve domain

### Issue 5: "OAuth signature error"

**Logs to Check:**
```
MWM DEBUG: OAuth Base String: GET&https%3A%2F%2Fexample.com...
MWM DEBUG: Signing Key (partial): abc123...&xyz789...
MWM DEBUG: OAuth Signature: ABC123...
MWM API Response Code: 401
MWM API Response Body: {"message":"oauth_problem=signature_invalid"}
```

**Possible Causes:**

1. **Wrong Consumer Secret**
   - Check: Matches Magento integration exactly
   - Re-copy from Magento

2. **Wrong Access Token Secret**
   - Check: Matches Magento integration exactly
   - Re-copy from Magento

3. **Double Encoding**
   - Should be fixed in current version
   - Check: You're using latest plugin version

4. **Timestamp Drift**
   - Check: Server time is correct
   - Check: WordPress server time sync
   - OAuth timestamps must be within 5 minutes

## Debugging Checklist

Use this checklist when troubleshooting:

### Credentials
- [ ] Store URL is correct (with https:// or http://)
- [ ] API Version is correct (try both V1 and V2)
- [ ] Consumer Key matches Magento (no extra spaces)
- [ ] Consumer Secret matches Magento (complete value)
- [ ] Access Token matches Magento (no extra spaces)
- [ ] Access Token Secret matches Magento (complete value)

### Magento Setup
- [ ] Integration exists in Magento Admin
- [ ] Integration is activated (status: Active)
- [ ] Integration has at least Read permissions
- [ ] Integration hasn't been revoked/expired
- [ ] REST API is enabled in Magento
- [ ] Magento store is accessible

### Server/Network
- [ ] WordPress server can reach Magento store
- [ ] Firewall allows outbound HTTPS connections
- [ ] DNS resolves correctly
- [ ] SSL certificate is valid (or sslverify disabled)
- [ ] Server time is synchronized

### OAuth
- [ ] All 4 credentials are present
- [ ] oauth_timestamp is recent
- [ ] oauth_nonce is unique per request
- [ ] oauth_signature is generated
- [ ] Signature uses HMAC-SHA256
- [ ] Signing key format: `consumer_secret&access_token_secret`

## Sample Successful Connection Logs

```
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: Test Connection - Received credentials
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: Store URL: https://magento.example.com
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: API Version: V1
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: Consumer Key (partial): abc12345...xyz9
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: Access Token (partial): def67890...abc2
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: Creating MWM_API_Connector instance
[15-Jan-2025 10:30:45 UTC] MWM API Connector Constructor:
[15-Jan-2025 10:30:45 UTC]   Store URL: https://magento.example.com
[15-Jan-2025 10:30:45 UTC]   API Version: V1
[15-Jan-2025 10:30:45 UTC]   Consumer Key: abc12345...xyz9
[15-Jan-2025 10:30:45 UTC]   Consumer Secret Length: 32
[15-Jan-2025 10:30:45 UTC]   Access Token: def67890...abc2
[15-Jan-2025 10:30:45 UTC]   Access Token Secret Length: 32
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: Calling test_connection()
[15-Jan-2025 10:30:45 UTC] MWM API Request: GET https://magento.example.com/rest/default/V1/modules
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: Endpoint: /modules
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: Full URL: https://magento.example.com/rest/default/V1/modules
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: OAuth Timestamp: 1736945845
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: OAuth Nonce: AbC123De456
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: Sorted parameters for signature:
[15-Jan-2025 10:30:45 UTC]   oauth_consumer_key => abc123def456789...
[15-Jan-2025 10:30:45 UTC]   oauth_nonce => AbC123De456
[15-Jan-2025 10:30:45 UTC]   oauth_signature_method => HMAC-SHA256
[15-Jan-2025 10:30:45 UTC]   oauth_timestamp => 1736945845
[15-Jan-2025 10:30:45 UTC]   oauth_token => def678901234567...
[15-Jan-2025 10:30:45 UTC]   oauth_version => 1.0
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: OAuth Base String (first 200 chars): GET&https%3A%2F%2Fmagento.example.com%2Frest%2Fdefault%2FV1%2Fmodules&oauth_consumer_key%3Dabc123...
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: Signing Key (partial): xyz987abc654...&cba321fed987...
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: OAuth Signature (first 30 chars): YWJjZGVmZ2hpamtsbW5vcHFy...
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: OAuth Parameters:
[15-Jan-2025 10:30:45 UTC]   oauth_consumer_key: abc123def456789...
[15-Jan-2025 10:30:45 UTC]   oauth_token: def678901234567...
[15-Jan-2025 10:30:45 UTC]   oauth_signature_method: HMAC-SHA256
[15-Jan-2025 10:30:45 UTC]   oauth_timestamp: 1736945845
[15-Jan-2025 10:30:45 UTC]   oauth_nonce: AbC123De456
[15-Jan-2025 10:30:45 UTC]   oauth_version: 1.0
[15-Jan-2025 10:30:45 UTC]   oauth_signature: YWJjZGVmZ2hpamtsbW5vcHFy...
[15-Jan-2025 10:30:45 UTC] MWM DEBUG: Final URL with OAuth params (first 200 chars): https://magento.example.com/rest/default/V1/modules?oauth_consumer_key=abc123...
[15-Jan-2025 10:30:46 UTC] MWM API Response Code: 200
[15-Jan-2025 10:30:46 UTC] MWM API Response Body: {"items":[{"name":"Magento_Catalog","setup_version":"2.4.3"},{"name":"Magento_Store","setup_version":"2.4.3"},...]}
[15-Jan-2025 10:30:46 UTC] MWM DEBUG: test_connection result: Array
[15-Jan-2025 10:30:46 UTC] (
    [success] => true
    [message] => Connection successful! Magento API is accessible. Found 245 modules.
    [store_info] => Array
        (
            [modules_count] => 245
            [api_version] => V1
        )
)
```

## How to Share Debug Logs

When sharing logs for support:

1. **Sanitize sensitive data:**
   - Remove full Consumer Key (keep first 8 + last 4)
   - Remove full Consumer Secret (show length only)
   - Remove full Access Token (keep first 8 + last 4)
   - Remove full Access Token Secret (show length only)
   - Remove OAuth Signature (keep first 20 chars)
   - Remove Signing Key (keep first 20 chars)

2. **Include context:**
   - What action were you taking?
   - What error message did you see?
   - What API version setting?
   - Magento version?

3. **Share relevant sections:**
   - From "MWM DEBUG: Test Connection" to final result
   - Include error responses
   - Include request URL construction

## Getting Help

If you're still stuck after checking the logs:

1. Gather the debug log output
2. Sanitize sensitive credentials
3. Note the exact error message
4. Check the logs against this guide
5. Share sanitized logs in support request

---

**Remember:** Always disable debug mode (`WP_DEBUG false`) in production after troubleshooting!
