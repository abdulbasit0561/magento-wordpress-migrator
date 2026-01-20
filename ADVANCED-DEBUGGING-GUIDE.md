# Advanced Connector Debugging Guide

## Overview
When the "Invalid JSON response from connector" error occurs, this comprehensive debugging system will help identify the exact issue at every step.

---

## New Diagnostic Endpoints

### 1. Ping Endpoint (`?endpoint=ping`)
**Purpose:** Test if connector file is accessible (no authentication required)

**Test:**
```bash
curl "https://magento-site.com/magento-connector.php?endpoint=ping"
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Connector is accessible",
  "timestamp": 1642234567,
  "date": "2024-01-15 10:30:45"
}
```

**What It Tests:**
- ✅ Connector file exists and is accessible
- ✅ PHP is working
- ✅ Output buffering is functioning
- ✅ JSON encoding works
- ✅ No fatal errors in connector file

### 2. Debug Endpoint (`?endpoint=test_debug`)
**Purpose:** Test authentication and system info without loading Magento (requires API key)

**Test:**
```bash
curl "https://magento-site.com/magento-connector.php?endpoint=test_debug" \
  -H "X-Magento-Connector-Key: your-api-key"
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Debug test successful",
  "php_version": "7.4.3",
  "server_software": "Apache/2.4.41",
  "memory_limit": "256M",
  "mage_php_exists": true,
  "config_file_exists": true,
  "error_log_writable": true,
  "extensions": {
    "curl": true,
    "json": true,
    "mbstring": true
  }
}
```

**What It Tests:**
- ✅ API key authentication works
- ✅ Config file exists
- ✅ Magento files exist
- ✅ PHP extensions loaded
- ✅ Memory limits
- ✅ Error log is writable

### 3. Test Endpoint (`?endpoint=test`)
**Purpose:** Full connection test including Magento loading

**Test:**
```bash
curl "https://magento-site.com/magento-connector.php?endpoint=test" \
  -H "X-Magento-Connector-Key: your-api-key"
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Connection successful",
  "magento_version": "Magento 1"
}
```

**What It Tests:**
- ✅ Everything in test_debug
- ✅ Magento can be loaded
- ✅ Database connection works
- ✅ Full integration test

---

## Enhanced Logging

### WordPress Side Debug Log Format

Every request now logs:
```
MWM Connector: ==================================================
MWM Connector: REQUEST START
MWM Connector: Endpoint: test
MWM Connector: Full URL: https://magento.com/connector.php?endpoint=test
MWM Connector: API Key (first 8 chars): a1b2c3d4...
MWM Connector: Request args: Array (...)

MWM Connector: ✅ RESPONSE RECEIVED
MWM Connector: HTTP Status: 200
MWM Connector: Response Headers: Array (
    [content-type] => application/json
    [server] => Apache/2.4.41
)
MWM Connector: Content-Type: application/json
MWM Connector: Response body length: 156 bytes
MWM Connector: Response body (first 1000 chars): {"success":true...}

MWM Connector: ✅ JSON is valid
MWM Connector: Decoded data: Array (
    [success] => true
    [message] => Connection successful
    ...
)
MWM Connector: REQUEST END
MWM Connector: ==================================================
```

### Error Logging Format

```
MWM Connector: ==================================================
MWM Connector: ❌ WP ERROR
MWM Connector: Error code: http_request_failed
MWM Connector: Error message: Could not establish connection
MWM Connector: REQUEST END
MWM Connector: ==================================================
```

---

## 3-Step Connection Test Process

When user clicks "Test Connector Connection":

### Step 1: Ping Test
**Tests:** Basic connector accessibility

**Success:** ✅ Connector file is accessible
**Failure:** ❌ Shows which step failed and why

**Log Entry:**
```
MWM: ==================================================
MWM: CONNECTOR TEST START
MWM: Connector URL: https://...
MWM: API Key length: 64
MWM: Step 1: Testing ping endpoint...
MWM: ✅ Ping successful: Connector is accessible
```

### Step 2: Debug Test
**Tests:** Authentication + System info

**Success:** ✅ Authentication works, system is configured
**Failure:** ❌ API key invalid or system misconfigured

**Log Entry:**
```
MWM: Step 2: Testing debug endpoint...
MWM: ✅ Debug endpoint successful
MWM: Debug info: Array (
    [php_version] => 7.4.3
    [mage_php_exists] => true
    ...
)
```

### Step 3: Full Connection Test
**Tests:** Magento loading + database

**Success:** ✅ Magento loaded successfully
**Failure:** ❌ Magento cannot be loaded

**Log Entry:**
```
MWM: Step 3: Testing full connection with Magento...
MWM: ✅ Full connection successful
MWM: ✅ ALL TESTS PASSED
MWM: CONNECTOR TEST END
MWM: ==================================================
```

---

## Debug Information Returned

On successful test, the response includes:

```json
{
  "success": true,
  "message": "Connection successful! Magento version: Magento 1",
  "magento_version": "Magento 1",
  "debug_info": {
    "php_version": "7.4.3",
    "server_software": "Apache/2.4.41",
    "memory_limit": "256M",
    "max_execution_time": "30",
    "mage_php_exists": true,
    "config_file_exists": true,
    "error_log_writable": true,
    "extensions": {
      "curl": true,
      "json": true,
      "mbstring": true
    }
  }
}
```

This allows users to see:
- PHP version compatibility
- Server information
- Required extensions status
- File permissions
- Memory limits

---

## Troubleshooting by Step

### Step 1 Failure: Ping Failed

**Possible Causes:**
1. Wrong connector URL
2. Connector file not uploaded
3. File permissions issue
4. Server blocking requests
5. DNS/hostname issues

**Check:**
```bash
# Can you reach the URL at all?
curl -I "https://magento-site.com/magento-connector.php?endpoint=ping"

# Does the file exist?
ssh server
ls -la /path/to/magento/magento-connector.php

# Check file permissions
chmod 644 magento-connector.php
```

### Step 2 Failure: Debug Test Failed

**Possible Causes:**
1. Wrong API key
2. Config file not created
3. File permissions on config
4. Authentication error

**Check:**
```bash
# Does config file exist?
ls -la /path/to/magento/connector-config.php

# Test API key manually
curl "https://magento-site.com/magento-connector.php?endpoint=test_debug" \
  -H "X-Magento-Connector-Key: your-key-here"

# Check config file contents
cat connector-config.php
# Should show: define("MAGENTO_CONNECTOR_KEY", "...");
```

### Step 3 Failure: Full Connection Failed

**Possible Causes:**
1. Magento cannot be loaded
2. Database connection issue
3. Missing Magento files
4. PHP memory limit too low
5. Magento initialization errors

**Check:**
```bash
# Check Magento files
ls -la /path/to/magento/app/Mage.php

# Check PHP memory limit
php -i | grep memory_limit

# Check Magento error logs
tail -f /path/to/magento/var/log/exception.log

# Test Magento loading manually
php -r 'require "app/Mage.php"; Mage::app(); echo "OK\n";'
```

---

## Error Messages and Meanings

### "Ping failed: Invalid JSON response"
- **Meaning:** Connector returned something that's not valid JSON
- **Debug:** Check connector error log for PHP errors
- **Fix:** Fix PHP errors in connector file

### "Authentication failed or debug endpoint error"
- **Meaning:** API key is wrong or config file missing
- **Debug:** Verify API key matches exactly
- **Fix:** Regenerate API key or check config file

### "Connection failed: Failed to initialize Magento"
- **Meaning:** Magento loading threw an exception
- **Debug:** Check Magento exception log
- **Fix:** Resolve Magento initialization issue

### "Invalid JSON response from connector. JSON Error: Syntax error"
- **Meaning:** PHP warnings appearing before JSON
- **Debug:** Check raw response in debug log
- **Fix:** Fix PHP code causing warnings

---

## Manual Testing Commands

### Test 1: Direct File Access
```bash
curl "https://magento-site.com/magento-connector.php?endpoint=ping"
```

Should return clean JSON immediately.

### Test 2: With Authentication
```bash
curl "https://magento-site.com/magento-connector.php?endpoint=test_debug" \
  -H "X-Magento-Connector-Key: your-api-key-here" \
  -v
```

The `-v` flag shows all headers and connection details.

### Test 3: Full Connection
```bash
curl "https://magento-site.com/magento-connector.php?endpoint=test" \
  -H "X-Magento-Connector-Key: your-api-key-here"
```

Should return Magento version info.

### Test 4: Check Response Headers
```bash
curl -I "https://magento-site.com/magento-connector.php?endpoint=ping"
```

Check Content-Type is `application/json`.

---

## Log Files to Monitor

### WordPress Side:
```
wp-content/debug.log
```

Look for:
- `MWM Connector:` entries
- Request/response details
- HTTP status codes
- JSON validation results

### Magento Side:
```
/path/to/magento/var/log/connector-errors.log
```

Look for:
- PHP errors during initialization
- Database connection errors
- Magento loading errors

```
/path/to/magento/var/log/connector-access.log
```

Look for:
- All API requests
- Timestamps
- IP addresses
- Endpoints called

---

## Diagnostic Tool Usage

1. **Upload diagnostic script:**
   ```bash
   cp test-connector-communication.php /path/to/wordpress/
   ```

2. **Visit diagnostic page:**
   ```
   https://your-site.com/test-connector-communication.php
   ```

3. **Review all test sections:**
   - Configuration check
   - File accessibility
   - Response headers
   - Raw response body
   - JSON validation
   - Recent log entries

4. **Follow recommendations:**
   - Each section shows specific next steps
   - Links to relevant resources
   - File locations to check

---

## Quick Reference

### Test Endpoints Summary:
| Endpoint | Auth Required | Magento Load | Purpose |
|----------|---------------|--------------|---------|
| `ping` | ❌ No | ❌ No | File accessibility |
| `test_debug` | ✅ Yes | ❌ No | Auth + system info |
| `test` | ✅ Yes | ✅ Yes | Full integration test |

### Error Location Guide:
| Symptom | Likely Location | Check First |
|---------|----------------|-------------|
| Can't reach URL | Network/Server | curl -I URL |
| Wrong Content-Type | Connector file | Headers in response |
| Auth failed | Config/API key | Verify API key |
| Magento load failed | Magento/Database | Magento error logs |
| JSON syntax error | PHP warnings | connector-errors.log |

---

## Status

✅ **COMPLETE** - Comprehensive multi-step debugging implemented.

**New Features:**
- ✅ Ping endpoint for basic connectivity
- ✅ Debug endpoint for system info
- ✅ Enhanced logging with full details
- ✅ 3-step connection test process
- ✅ Detailed error reporting by step
- ✅ Diagnostic information returned to user
- ✅ All HTTP headers logged
- ✅ Request/response bodies logged
- ✅ JSON validation status logged

**Result:**
Users can now pinpoint EXACTLY where the connection is failing! 🎯
