# WHMCS Kaza Wallet Payment Gateway

A WHMCS payment gateway plugin for Kaza Wallet payment processing.

**Status: ✅ WORKING! Solution found - activation requires Apps & Integrations step first.**

## ✅ SOLUTION FOUND!

**IMPORTANT**: The gateway must be activated through **Apps & Integrations** first, not directly in Payment Gateways.

### Correct Activation Process:
1. **First**: Setup → System Settings → Payment → **Apps & Integrations** 
2. **Then**: Setup → Payment Gateways (gateway will now appear here)

This explains why the gateway wasn't appearing despite all diagnostics passing!

## Features

- ✅ **Real Kaza Wallet API integration** - Uses actual Kaza Wallet endpoints
- ✅ **Payment link creation** - Creates payment links via Kaza Wallet API
- ✅ **Webhook processing** - Handles payment notifications automatically
- ✅ **Signature verification** - Secure HMAC-SHA512 verification
- ✅ **Refund support** - Processes refunds via withdrawal requests
- ✅ **WHMCS-compliant** - Follows all WHMCS standards
- ✅ **Third-party gateway** - Redirects users to Kaza Wallet payment page

## Quick Installation

### Step 1: Upload Files
1. Upload **only** `kazawallet.php` to your WHMCS `modules/gateways/` directory
2. Upload `callback/kazawallet.php` to your WHMCS `modules/gateways/callback/` directory

### Step 2: Activate the Gateway ⚠️ IMPORTANT
**Correct activation process:**

1. **First**: Login to WHMCS Admin
2. **Go to**: Setup → System Settings → Payment → **Apps & Integrations**
3. **Find**: "Kaza Wallet Payment Gateway" and activate it there
4. **Then**: Go to Setup → Payment Gateways 
5. **Find**: "Kaza Wallet Payment Gateway" (it will now appear here)
6. **Click**: "Activate" and configure with your API credentials

**Note**: The gateway MUST be activated in Apps & Integrations first, or it won't appear in Payment Gateways!

## Configuration

| Field | Description |
|-------|-------------|
| API Key | Your Kaza Wallet API Key (x-api-key) |
| API Secret | Your Kaza Wallet API Secret (x-api-secret) |
| Test Mode | Enable for testing |

**Important**: Get your API credentials from your Kaza Wallet merchant dashboard.

## File Structure

```
whmcs-root/
├── modules/gateways/
│   └── kazawallet.php              # Main gateway (REQUIRED)
└── modules/gateways/callback/
    └── kazawallet.php              # Callback handler (REQUIRED)
```

## Troubleshooting

### Gateway Not Appearing?

⚠️ **MOST COMMON ISSUE**: Gateway must be activated in **Apps & Integrations** first!

**Correct process:**
1. **Setup → System Settings → Payment → Apps & Integrations** (activate here first)
2. **Then** Setup → Payment Gateways (gateway will appear here)

**Other checks:**
1. **Check file location** - Ensure `kazawallet.php` is in `modules/gateways/`
2. **Check file permissions** - Should be readable (644 or 755)
3. **Clear WHMCS cache** - Admin → System Settings → General Settings → Other → Clear Template Cache
4. **Refresh browser** and check Payment Gateways again

### Common Issues

- **File in wrong directory** - Must be in `modules/gateways/`
- **Incorrect filename** - Must be exactly `kazawallet.php`
- **Forgot Apps & Integrations step** - Must activate there first
- **WHMCS cache** - Clear template cache and refresh browser

## Support

1. Check WHMCS gateway logs: Admin → Utilities → Logs → Gateway Log
2. Ensure you're using the exact files from this repository  
3. Follow the correct activation process: Apps & Integrations first, then Payment Gateways

## Version History

- **v2.0.0** - 🚀 FULL API INTEGRATION: Real Kaza Wallet API implementation with payment links, webhooks, and refunds
- **v1.0.5** - 🎉 WORKING VERSION: Found activation solution (Apps & Integrations first), cleaned up unnecessary files
- **v1.0.4** - Created comprehensive diagnostic tools 
- **v1.0.3** - Simplified to match WHMCS sample exactly
- **v1.0.2** - Consolidated version following official WHMCS sample gateway structure
- **v1.0.1** - Fixed PHP 5.6 compatibility 
- **v1.0.0** - Initial release

## Requirements

- WHMCS 7.0 or higher
- PHP 5.6 or higher
- Standard WHMCS installation

## Notes

- **Full Kaza Wallet API integration** - Uses real payment links and webhooks
- **Secure payment processing** - HMAC-SHA512 signature verification
- **Automatic payment confirmation** - Webhooks update invoice status automatically  
- **Refund support** - Creates withdrawal requests for refunds
- **Production ready** - Ready for live payments with proper API credentials

## API Integration Details

### Payment Flow:
1. Customer clicks "Pay Now" 
2. Gateway creates payment link via Kaza Wallet API
3. Customer redirected to Kaza Wallet payment page
4. After payment, Kaza Wallet sends webhook to your site
5. Callback verifies signature and marks invoice as paid

### Webhook URL:
Your webhook URL will be: `https://yourdomain.com/modules/gateways/callback/kazawallet.php`

**⚠️ Important**: Configure this webhook URL in your Kaza Wallet merchant dashboard!

---

**✅ Gateway now works! Remember the Apps & Integrations activation step.**
