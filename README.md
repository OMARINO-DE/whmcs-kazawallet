# WHMCS Kaza Wallet Payment Gateway

A comprehensive WHMCS payment gateway plugin for Kaza Wallet payment processing.

## Features

- ✅ Secure payment processing via Kaza Wallet API
- ✅ Single API endpoint (no sandbox/live separation)
- ✅ Automatic callback URL generation and display
- ✅ Admin panel configuration with copy-to-clipboard functionality
- ✅ Transaction logging and error handling
- ✅ Refund support via withdrawal API
- ✅ Payment status verification with HMAC signature validation
- ✅ Responsive admin interface with status indicators

## Quick Installation Guide

### For Basic Setup (Recommended):
1. Upload **only** `kazawallet.php` to `modules/gateways/`
2. Activate "Kaza Wallet" in WHMCS Admin → Setup → Payment Gateways
3. Configure with your API Key and API Secret
4. Test with a small payment

### For Full Features:
1. Upload `kazawallet.php` to `modules/gateways/`
2. Upload `callback/kazawallet.php` to `modules/gateways/callback/`
3. Upload `hooks/kazawallet_admin.php` to `includes/hooks/`
4. Configure as above

## Installation

### Step 1: Upload Files

1. Copy **only** `kazawallet.php` to your WHMCS `modules/gateways/` directory
2. Copy `callback/kazawallet.php` to your WHMCS `modules/gateways/callback/` directory *(optional)*
3. Copy `hooks/kazawallet_admin.php` to your WHMCS `includes/hooks/` directory *(optional)*

**Important**: 
- ❌ **Do NOT** copy `config.php` or `helpers.php` to the `modules/gateways/` directory
- ✅ **Only** the `kazawallet.php` file should be in `modules/gateways/`
- ✅ The main `kazawallet.php` file is self-contained and includes all necessary code

### Step 2: Activate the Gateway

1. Login to your WHMCS Admin Panel
2. Navigate to **Setup → Payments → Payment Gateways**
3. Find "Kaza Wallet" in the list and click **Activate**

### Step 3: Configure the Gateway

1. Click **Manage** next to Kaza Wallet
2. Enter your **API Key** from your Kaza Wallet merchant dashboard
3. Enter your **API Secret** from your Kaza Wallet merchant dashboard
4. Choose **Test Mode** for testing or uncheck for live payments
5. The **Callback URL** will be automatically generated
6. Customize the **Payment Description** if needed
7. Click **Save Changes**

### Step 4: Configure Webhooks in Kaza Wallet

1. Copy the **Callback URL** from the WHMCS configuration (use the copy button)
2. Login to your Kaza Wallet merchant dashboard
3. Navigate to webhook/callback settings
4. Add the copied callback URL
5. Ensure the webhook is set to send payment notifications

## Configuration Options

| Setting | Description | Required |
|---------|-------------|----------|
| API Key | Your Kaza Wallet API key (x-api-key header) | ✅ Yes |
| API Secret | Your Kaza Wallet API secret for webhook verification | ✅ Yes |
| Test Mode | Enable for testing, disable for live payments | No |
| Callback URL | Auto-generated webhook URL (copy to Kaza Wallet dashboard) | Auto |
| Payment Description | Description shown on payment page | No |

## File Structure

```
whmcs-root/
├── modules/gateways/
│   └── kazawallet.php              # Main gateway module (REQUIRED)
├── modules/gateways/callback/
│   └── kazawallet.php              # Payment callback handler (OPTIONAL)
└── includes/hooks/
    └── kazawallet_admin.php        # Admin interface enhancements (OPTIONAL)
```

**Important**: Do NOT place `config.php` or `helpers.php` in the `modules/gateways/` directory as WHMCS will try to load them as gateway modules and cause errors.

## Testing

1. Enable **Test Mode** in the gateway configuration
2. Use your Kaza Wallet API credentials
3. Create a test invoice and attempt payment
4. Verify payment status and callback processing
5. Check transaction logs in WHMCS admin

## Troubleshooting

### Common Issues

**Gateway Module Error: Configuration function not found**
- Remove `config.php` and `helpers.php` from `modules/gateways/` directory
- WHMCS tries to load all PHP files in that directory as gateway modules
- Only `kazawallet.php` should be in the `modules/gateways/` directory

**Payment Link Not Generated**
- Verify API credentials are correct
- Check API key format and permissions
- Review WHMCS system logs for errors

**Callbacks Not Working**
- Ensure callback URL is correctly configured in Kaza Wallet dashboard
- Verify webhook URL is accessible (test in browser)
- Check WHMCS gateway logs for callback errors

**Signature Validation Failures**
- Confirm API secret matches in both WHMCS and Kaza Wallet
- Ensure webhook payload format is correct
- Verify HMAC signature calculation

### Log Files

- WHMCS Gateway Logs: `Admin → Utilities → Logs → Gateway Log`
- System Activity: `Admin → Utilities → Logs → Activity Log`

## API Endpoints

The plugin uses the following Kaza Wallet API endpoint:

- **API Base URL**: `https://outdoor.kasroad.com`

### Endpoints Used

- `POST /wallet/createPaymentLink` - Create payment link
- `POST /wallet/createWithdrawalRequest` - Process withdrawals/refunds

## Security Features

- ✅ HMAC-SHA512 signature verification for webhooks
- ✅ SSL/TLS encryption for API calls
- ✅ Secure credential storage
- ✅ Transaction validation
- ✅ Invoice amount verification

## Support

For technical support:

1. **Kaza Wallet API Issues**: Contact [Kaza Wallet Support](https://www.kazawallet.com/support)
2. **WHMCS Integration Issues**: Check WHMCS documentation or community forums
3. **Plugin Issues**: Review this documentation and check logs

## Version History

- **v1.0.0** - Initial release with full payment processing, callbacks, and admin interface

## License

Mozilla Public License Version 2.0 - see LICENSE file for details.

## Requirements

- WHMCS 7.0 or higher
- PHP 7.0 or higher
- cURL extension enabled
- Valid Kaza Wallet merchant account
- SSL certificate (recommended for production)

---

**Note**: Always test with small amounts before processing live payments.
