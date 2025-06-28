# WHMCS Kaza Wallet Gateway - Advanced Troubleshooting

## Current Status
- ✅ Gateway file exists and has correct syntax
- ✅ All required functions are present
- ✅ File permissions are correct
- ✅ No BOM or encoding issues detected
- ❌ Gateway still not appearing in WHMCS admin panel

## Next Steps to Investigate

### 1. WHMCS Cache Issues
The most common cause of gateways not appearing is WHMCS caching:

**Clear All Caches:**
1. Go to WHMCS Admin → Configuration → System Settings → General Settings
2. Under "Other" tab, click "Clear Template Cache"
3. Also clear browser cache (Ctrl+F5)
4. Restart web server if possible

**Clear Smarty Cache (if accessible):**
```bash
# Navigate to WHMCS root directory
rm -rf templates_c/*
```

### 2. Check WHMCS Error Logs
Look for any gateway-related errors:

**Locations to check:**
- WHMCS Admin → Utilities → Logs → Activity Log
- WHMCS Admin → Utilities → Logs → Module Log
- Server error logs (Apache/Nginx)
- PHP error logs

**Look for entries containing:**
- "gateway"
- "kazawallet"
- "payment"
- PHP fatal errors during admin page loads

### 3. Verify File Placement
Ensure the file is in the exact correct location:

**Expected path:** `[WHMCS_ROOT]/modules/gateways/kazawallet.php`

**Check:**
- File is directly in `modules/gateways/` (not in a subdirectory)
- Filename is exactly `kazawallet.php` (case-sensitive on Linux)
- File permissions allow web server to read (644 or 755)

### 4. Test in Minimal Environment
Create a minimal test to verify WHMCS can load the gateway:

1. Create a test script in WHMCS root:
```php
<?php
// Place this file in WHMCS root as gateway-test.php
include_once 'modules/gateways/kazawallet.php';

if (function_exists('kazawallet_config')) {
    echo "Gateway functions loaded successfully!<br>";
    $config = kazawallet_config();
    echo "Config returned " . count($config) . " settings<br>";
    print_r($config['FriendlyName']);
} else {
    echo "Gateway functions NOT loaded!<br>";
}
?>
```

### 5. Check WHMCS Version Compatibility
Verify your WHMCS version supports the gateway format:

**Required WHMCS version:** 6.0 or higher for the current format
**Check your version:** WHMCS Admin → Help → System Info

### 6. Database Issues
Sometimes WHMCS caches gateway information in the database:

**Clear gateway cache (if you have database access):**
```sql
-- Use with caution - backup first!
DELETE FROM tblconfiguration WHERE setting LIKE '%gateway%cache%';
```

### 7. File Integrity Check
Verify the file exactly matches the WHMCS sample:

**Use web-test.php:** Navigate to `http://yourwhmcs.com/modules/gateways/web-test.php`

### 8. Alternative Testing Method
Try renaming the gateway and see if WHMCS picks it up:

1. Rename `kazawallet.php` to `testgateway.php`
2. Replace all function prefixes: `kazawallet_` → `testgateway_`
3. Update DisplayName to "Test Gateway"
4. Check if "Test Gateway" appears in admin

### 9. Server Configuration Issues
Check for server-level restrictions:

**PHP restrictions:**
- `include_once` allowed
- `function_exists` allowed
- No security modules blocking file includes

**File system:**
- No symbolic links
- Correct ownership (web server user)
- No extended attributes or special flags

### 10. WHMCS Configuration
Check WHMCS settings that might affect gateway loading:

**Admin → Configuration → System Settings → General Settings:**
- "Enable SSL" setting
- "WHMCS System URL" correctness
- "Cron Last Run" (ensure cron is working)

## Debugging Commands

### Check file from server command line:
```bash
# Check file exists and is readable
ls -la /path/to/whmcs/modules/gateways/kazawallet.php

# Check PHP syntax
php -l /path/to/whmcs/modules/gateways/kazawallet.php

# Test loading functions
php -r "include '/path/to/whmcs/modules/gateways/kazawallet.php'; var_dump(function_exists('kazawallet_config'));"
```

### PowerShell commands (Windows):
```powershell
# Check file
Get-ChildItem "kazawallet.php" | Format-List

# Check first bytes for encoding issues
Get-Content "kazawallet.php" -Encoding Byte | Select-Object -First 10
```

## Last Resort Solutions

If all else fails:

1. **Contact WHMCS Support** - They can check for known issues with your version
2. **Ask on WHMCS Community** - Other developers may have encountered this
3. **Try a different gateway name** - Some reserved words might cause conflicts
4. **Reinstall WHMCS** - Nuclear option, but might fix corrupted core files

## Files for Support
If contacting support, provide:
- `kazawallet.php` (the gateway file)
- `web-test.php` results
- WHMCS version and PHP version
- Error logs from WHMCS and server
- Screenshots of admin panel gateway list

## Current File Status
✅ Gateway file is clean and follows WHMCS standards
✅ All required functions present
✅ No syntax errors detected
✅ File permissions correct
❌ Gateway not appearing (investigating deeper issues)

Last updated: $(Get-Date)
