# "Invalid JSON Response" Error Fix

## Problem
Users were getting generic "Invalid JSON response from connector" errors with no details about what went wrong. This made debugging nearly impossible.

## Root Causes

1. **PHP Warnings Corrupting JSON** - PHP warnings/notices were being output before JSON, breaking the JSON structure
2. **Poor Error Messages** - Generic error messages didn't show what was actually received
3. **No Debugging Info** - No logging of the actual response body
4. **Missing Output Buffering** - No protection against unexpected output

## Solutions Implemented

### 1. Enhanced WordPress Connector Client Error Handling

**File:** `includes/class-mwm-connector-client.php`

All JSON decoding calls now:
- Log the full response body to debug.log
- Show the specific JSON error message
- Include first 200 chars of raw response in error
- Log JSON parsing errors

**Before:**
```php
if (json_last_error() !== JSON_ERROR_NONE) {
    return new WP_Error('invalid_json', 'Invalid JSON response from connector');
}
```

**After:**
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

**Methods Updated:**
- `test_connection()`
- `get_products()`
- `get_product()`
- `get_products_count()`
- `get_categories()`
- `get_category()`
- `get_categories_count()`

### 2. Added Output Buffering to Magento Connector

**File:** `magento-connector.php`

**Changes:**
1. **Immediate output buffering** - Starts at the very top of the file
2. **Custom error handler** - Catches all PHP errors and logs them instead of outputting
3. **Clean buffer before JSON** - `ob_end_clean()` called before every JSON response
4. **Error handler restoration** - Restore original error handler before output

**Code Added:**
```php
// Start output buffering IMMEDIATELY
ob_start();

// Set custom error handler to catch PHP errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("MAGENTO CONNECTOR PHP Error: [$errno] $errstr in $errfile on line $errline");
    return true; // Don't execute PHP internal error handler
});
```

**Updated send_response() function:**
```php
function send_response($data, $status_code = 200) {
    // Clean output buffer to prevent any PHP warnings from corrupting JSON
    ob_end_clean();

    // Restore error handler
    restore_error_handler();

    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
```

### 3. Enhanced Exception Handling

**File:** `magento-connector.php`

Router now catches both `Exception` and `Error` (PHP 7+):

```php
} catch (Exception $e) {
    error_log("MAGENTO CONNECTOR Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    send_error('Server error: ' . $e->getMessage(), 500);
} catch (Error $e) {
    // Catch PHP 7+ Errors (TypeError, ParseError, etc.)
    error_log("MAGENTO CONNECTOR Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    send_error('Server error: ' . $e->getMessage(), 500);
}
```

## Error Messages Now Include

### WordPress Side:
- ✅ JSON error type (Syntax error, Malformed JSON, etc.)
- ✅ JSON error message
- ✅ First 200 characters of raw response
- ✅ Full response body in debug.log

### Magento Connector Side:
- ✅ All PHP errors logged to `var/log/connector-errors.log`
- ✅ File and line number of errors
- ✅ Exceptions and Errors both caught
- ✅ Output buffering prevents warnings from corrupting JSON

## Debugging Workflow

### When JSON Error Occurs:

1. **Check WordPress debug.log:**
   ```
   wp-content/debug.log
   ```
   You'll see:
   ```
   MWM Connector: Invalid JSON response
   MWM Connector: Response body: <full response>
   MWM Connector: JSON error: Syntax error
   ```

2. **Check Magento connector error log:**
   ```
   /var/log/connector-errors.log
   ```
   You'll see:
   ```
   MAGENTO CONNECTOR PHP Error: [8] Undefined variable: xyz in /path/to/connector.php on line 123
   ```

3. **User sees detailed error:**
   ```
   Invalid JSON response from connector.
   JSON Error: Syntax error.
   Raw response (first 200 chars): <br />
   <b>Warning</b>:  Undefined variable: product_id in <b>/path/to/magento-connector.php</b> on line <b>456</b><br />
   {"success": true, ...
   ```

## Testing

### Test 1: Valid JSON Response
```bash
curl "https://magento-site.com/magento-connector.php?endpoint=test"
```

Expected:
```json
{"success":true,"message":"Connection successful","magento_version":"Magento 1"}
```

### Test 2: Invalid Endpoint (should still be valid JSON)
```bash
curl "https://magento-site.com/magento-connector.php?endpoint=invalid"
```

Expected:
```json
{"success":false,"message":"Invalid endpoint. Valid endpoints: ..."}
```

### Test 3: Missing API Key (should still be valid JSON)
```bash
curl "https://magento-site.com/magento-connector.php?endpoint=products"
```

Expected:
```json
{"success":false,"message":"API key missing..."}
```

## Common JSON Errors and What They Mean

### "Syntax error"
- PHP warning/notice appeared before JSON
- Check `var/log/connector-errors.log` for the PHP error
- Fix the PHP error in the connector

### "Malformed UTF-8 characters"
- Database encoding issue
- Character set mismatch
- Check Magento database connection encoding

### "Unexpected character"
- BOM (Byte Order Mark) in PHP files
- Whitespace before <?php tag
- Check connector file has no BOM

### "Control character error"
- Binary data in JSON string
- Unescaped special characters
- Sanitize data before JSON encoding

## Prevention Best Practices

### For Magento Connector:
1. ✅ Always use output buffering for API endpoints
2. ✅ Set custom error handlers
3. ✅ Log all errors instead of displaying
4. ✅ Clean output buffer before JSON
5. ✅ Use try-catch blocks with both Exception and Error
6. ✅ Validate data before JSON encoding

### For WordPress Client:
1. ✅ Log raw response when JSON fails
2. ✅ Show specific JSON error messages
3. ✅ Include sample of raw response in errors
4. ✅ Log to debug.log
5. ✅ Handle both WP_Error and exceptions

## Files Modified

1. **includes/class-mwm-connector-client.php**
   - Enhanced all JSON decoding with detailed error logging
   - Added raw response display in error messages
   - Log full response body to debug.log

2. **magento-connector.php**
   - Added output buffering at file start
   - Added custom error handler
   - Updated send_response() to clean buffer
   - Enhanced exception handling to catch Errors too
   - Added error logging with file/line info

## Performance Impact

- **Minimal**: Output buffering has negligible performance impact
- **Better**: Error logging helps identify and fix issues faster
- **Cleaner**: Prevents corrupted JSON from being sent

## Security Considerations

- ✅ Error messages don't expose sensitive data (API keys, passwords)
- ✅ Raw response truncated to 200 chars in user-facing errors
- ✅ Full response only in debug.log (server-side)
- ✅ File paths shown in errors are acceptable (not sensitive)

## Status

✅ **COMPLETE** - JSON errors now show detailed information for debugging.

### What Users See Now:
- Clear error messages explaining what went wrong
- JSON error type and message
- Sample of the problematic response
- Instructions to check logs for details

### What Developers See Now:
- Full response body in debug.log
- Exact JSON error that occurred
- PHP errors in connector error log
- File and line numbers of errors

### Result:
Faster debugging, clearer issues, easier fixes! 🎉
