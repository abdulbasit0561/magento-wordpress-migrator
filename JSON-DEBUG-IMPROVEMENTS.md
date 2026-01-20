# JSON Error Debug Improvements - Complete Summary

## Overview
Implemented comprehensive debugging and error handling for "Invalid JSON response from connector" errors. This includes better error messages, extensive logging, and diagnostic tools.

---

## Changes Made

### 1. Magento Connector (magento-connector.php)

#### A. Enhanced `load_magento()` Function (Lines 158-188)
**Problem:** `Mage::app()` can output content during initialization, corrupting JSON.

**Solution:**
```php
function load_magento() {
    // ... path checks ...

    // Magento 1
    require_once $mage_path;

    // Suppress any output from Magento initialization
    ob_start();

    // Initialize Magento app with error suppression
    try {
        @Mage::app();  // Error suppression operator
    } catch (Exception $e) {
        ob_end_clean();
        send_error('Failed to initialize Magento: ' . $e->getMessage(), 500);
    }

    // Clean any output from Magento initialization
    ob_end_clean();

    return array('version' => 1);
}
```

**Benefits:**
- ✅ Catches any output from Magento initialization
- ✅ Exception handling for Magento loading failures
- ✅ Clean output buffer before returning

#### B. Enhanced `send_response()` Function (Lines 273-301)
**Problem:** No validation that JSON encoding succeeded.

**Solution:**
```php
function send_response($data, $status_code = 200) {
    // Clean output buffer
    ob_end_clean();
    restore_error_handler();

    // Encode data to JSON
    $json = json_encode($data);

    // Check if JSON encoding succeeded
    if ($json === false) {
        error_log("MAGENTO CONNECTOR: JSON encode failed - " . json_last_error_msg());
        error_log("MAGENTO CONNECTOR: Data that failed to encode: " . print_r($data, true));

        // Send error response
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'message' => 'Internal server error: Failed to encode response'
        ));
        exit;
    }

    http_response_code($status_code);
    header('Content-Type: application/json');
    echo $json;
    exit;
}
```

**Benefits:**
- ✅ Validates JSON encoding before sending
- ✅ Logs failed encoding attempts with full data
- ✅ Returns error response if encoding fails
- ✅ Prevents sending malformed/empty JSON

---

### 2. WordPress Connector Client (class-mwm-connector-client.php)

#### A. Enhanced `make_request()` Function (Lines 329-364)
**Problem:** No visibility into what's being sent and received.

**Solution:**
```php
private function make_request($endpoint, $params = array()) {
    $url = $this->connector_url . '?endpoint=' . urlencode($endpoint);

    if (!empty($params)) {
        $url .= '&' . http_build_query($params);
    }

    // Debug: Log the request URL
    error_log('MWM Connector: Requesting URL: ' . $url);

    $args = array(
        'timeout' => $this->timeout,
        'headers' => array(
            'X-Magento-Connector-Key' => $this->api_key,
            'Accept' => 'application/json'
        ),
        'sslverify' => false
    );

    $response = wp_remote_get($url, $args);

    // Debug: Log response details
    if (is_wp_error($response)) {
        error_log('MWM Connector: WP Error: ' . $response->get_error_message());
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        error_log('MWM Connector: Response code: ' . $response_code);
        error_log('MWM Connector: Response body length: ' . strlen($body));
        error_log('MWM Connector: Response body (first 500 chars): ' . substr($body, 0, 500));
    }

    return $response;
}
```

**Benefits:**
- ✅ Logs every request URL
- ✅ Logs HTTP response code
- ✅ Logs response body length
- ✅ Logs first 500 chars of response
- ✅ Logs WP Errors if they occur

#### B. All JSON Decoding Calls (7 methods total)
**Methods Updated:**
- `test_connection()` - Line 57
- `get_products()` - Line 98
- `get_product()` - Line 138
- `get_products_count()` - Line 175
- `get_categories()` - Line 194
- `get_category()` - Line 258
- `get_categories_count()` - Line 295

**Enhanced Error Handling:**
```php
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('MWM Connector: Invalid JSON response');
    error_log('MWM Connector: Response body: ' . $body);
    error_log('MWM Connector: JSON error: ' . json_last_error_msg());
    return new WP_Error(
        'invalid_json',
        'Invalid JSON response from connector. ' .
        'JSON Error: ' . json_last_error_msg() . '. ' .
        'Raw response (first 200 chars): ' . substr($body, 0, 200)
    );
}
```

**Benefits:**
- ✅ Logs full response body to debug.log
- ✅ Shows specific JSON error type
- ✅ Shows first 200 chars to user
- ✅ Clear, actionable error messages

---

### 3. Diagnostic Tool (test-connector-communication.php)

**New diagnostic script that tests:**

1. **Connector File Accessibility**
   - Checks if connector URL is reachable
   - Shows HTTP status code
   - Verifies file exists

2. **Response Headers**
   - Shows all response headers
   - Validates Content-Type is application/json
   - Checks for CORS headers

3. **Raw Response Body**
   - Shows exact response from connector
   - Displays response length
   - Shows first 1000 characters

4. **JSON Validation**
   - Attempts to decode JSON
   - Shows JSON structure
   - Validates required fields exist
   - Explains JSON errors

5. **WordPress Debug Log**
   - Shows recent MWM Connector log entries
   - Filters for connector-related logs
   - Displays in readable format

6. **Recommendations**
   - Context-specific next steps
   - Links to relevant resources
   - File locations to check

**Usage:**
1. Upload `test-connector-communication.php` to WordPress root
2. Visit: `https://your-site.com/test-connector-communication.php`
3. Review all test sections
4. Follow recommendations

---

## Debugging Workflow

### When "Invalid JSON" Error Occurs:

#### Step 1: Check User-Facing Error
User sees:
```
Invalid JSON response from connector.
JSON Error: Syntax error.
Raw response (first 200 chars): <br />Warning: Undefined variable...
```

#### Step 2: Check WordPress Debug Log
`wp-content/debug.log` contains:
```
MWM Connector: Requesting URL: https://magento.com/connector.php?endpoint=test
MWM Connector: Response code: 200
MWM Connector: Response body length: 523
MWM Connector: Response body (first 500 chars): <br />Warning: ...
MWM Connector: Invalid JSON response
MWM Connector: Response body: <full response>
MWM Connector: JSON error: Syntax error
```

#### Step 3: Check Magento Connector Log
`/var/log/connector-errors.log` contains:
```
MAGENTO CONNECTOR PHP Error: [8] Undefined variable: product_id in /path/to/connector.php on line 456
```

#### Step 4: Run Diagnostic Tool
Visit `test-connector-communication.php` to see:
- Visual breakdown of entire request/response
- JSON structure validation
- Recent log entries
- Specific recommendations

#### Step 5: Fix Issue
Based on diagnostic information, fix the underlying issue (PHP error, encoding issue, etc.)

---

## Common Issues and Solutions

### Issue 1: PHP Warning Before JSON
**Symptoms:**
```
Raw response: <br />Warning: Undefined variable: x in...
```

**Solution:**
1. Check Magento connector error log for full error
2. Fix the PHP code causing the warning
3. The output buffering should catch this, but fix the root cause

### Issue 2: Magento Initialization Output
**Symptoms:**
```
Raw response: Magento version...{"success":true}
```

**Solution:**
1. The enhanced `load_magento()` function now suppresses this
2. Update magento-connector.php with latest version
3. Check if Magento is outputting during initialization

### Issue 3: Empty Response
**Symptoms:**
```
Response body length: 0
```

**Solution:**
1. Check if connector file path is correct
2. Verify API key authentication is working
3. Check PHP error logs for fatal errors
4. Verify file permissions on connector

### Issue 4: Malformed JSON
**Symptoms:**
```
JSON Error: Syntax error
Raw response: {"success":true, "message":"hello" // missing quote}
```

**Solution:**
1. The enhanced `send_response()` now validates JSON encoding
2. Check for malformed data being passed to send_response
3. Look for encoding issues (UTF-8 vs other)

### Issue 5: Wrong Content-Type
**Symptoms:**
```
Content-Type: text/html
Expected: application/json
```

**Solution:**
1. Check that send_response() is being used
2. Look for code that outputs before send_response()
3. Verify no other code sets headers

---

## File Checklist

### WordPress Side:
- ✅ `includes/class-mwm-connector-client.php` - Enhanced error reporting
- ✅ `test-connector-communication.php` - Diagnostic tool (NEW)

### Magento Side:
- ✅ `magento-connector.php` - Output buffering + validation

### Logs to Check:
- ✅ `wp-content/debug.log` - WordPress debug log
- ✅ `/var/log/connector-errors.log` - Magento connector errors
- ✅ `/var/log/connector-access.log` - Magento connector access

---

## Testing Procedure

### 1. Basic Connection Test
```bash
# Test connector directly
curl "https://magento-site.com/magento-connector.php?endpoint=test" \
  -H "X-Magento-Connector-Key: your-api-key"
```

Expected output:
```json
{"success":true,"message":"Connection successful","magento_version":"Magento 1"}
```

### 2. WordPress Test
1. Go to WordPress Admin → Magento → Settings
2. Configure connector URL and API key
3. Click "Test Connector Connection"
4. Should see: ✅ Connection successful! Magento version: Magento 1

### 3. Diagnostic Tool Test
1. Visit `/test-connector-communication.php`
2. Review all test sections
3. All tests should pass (green ✅)

### 4. Migration Test
1. Try migrating a small batch (1-2 products)
2. Check logs for any errors
3. Verify products imported correctly

---

## Status

✅ **COMPLETE** - Comprehensive debugging and error handling implemented.

**Summary:**
- Magento connector now suppresses output during initialization
- WordPress client logs all request/response details
- JSON encoding is validated before sending
- User-friendly error messages with raw response samples
- Diagnostic tool provides visual debugging interface
- All errors logged to appropriate log files

**Result:**
Faster debugging, clearer errors, easier fixes! 🎉
