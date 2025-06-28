# WHMCS Kaza Wallet Payment Gateway

A comprehensive WHMCS payment gateway plugin for Kaza Wallet payment processing.

## Features

- ✅ Secure payment processing via Kaza Wallet API
- ✅ Support for both sandbox and live environments
- ✅ Automatic callback URL generation and display
- ✅ Admin panel configuration with copy-to-clipboard functionality
- ✅ Transaction logging and error handling
- ✅ Refund support
- ✅ Payment status verification with hash validation
- ✅ Responsive admin interface with status indicators

## Installation

### Step 1: Upload Files

1. Copy `kazawallet.php` to your WHMCS `modules/gateways/` directory
2. Copy `callback/kazawallet.php` to your WHMCS `modules/gateways/callback/` directory
3. Copy `hooks/kazawallet_admin.php` to your WHMCS `includes/hooks/` directory

### Step 2: Activate the Gateway

1. Login to your WHMCS Admin Panel
2. Navigate to **Setup → Payments → Payment Gateways**
3. Find "Kaza Wallet" in the list and click **Activate**

### Step 3: Configure the Gateway

1. Click **Manage** next to Kaza Wallet
2. Enter your **API Key** from your Kaza Wallet merchant dashboard
3. Enter your **Secret Key** from your Kaza Wallet merchant dashboard
4. Choose **Sandbox Mode** for testing or uncheck for live payments
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
| API Key | Your Kaza Wallet API key | ✅ Yes |
| Secret Key | Your Kaza Wallet secret key for webhook verification | ✅ Yes |
| Sandbox Mode | Enable for testing, disable for live payments | No |
| Callback URL | Auto-generated webhook URL (copy to Kaza Wallet dashboard) | Auto |
| Payment Description | Description shown on payment page | No |

## File Structure

```
whmcs-root/
├── modules/gateways/
│   ├── kazawallet.php              # Main gateway module
│   └── callback/
│       └── kazawallet.php          # Payment callback handler
└── includes/hooks/
    └── kazawallet_admin.php        # Admin interface enhancements
```

## Testing

1. Enable **Sandbox Mode** in the gateway configuration
2. Use Kaza Wallet sandbox credentials
3. Create a test invoice and attempt payment
4. Verify payment status and callback processing
5. Check transaction logs in WHMCS admin

## Troubleshooting

### Common Issues

**Payment Link Not Generated**
- Verify API credentials are correct
- Check if sandbox/live mode matches your credentials
- Review WHMCS system logs for errors

**Callbacks Not Working**
- Ensure callback URL is correctly configured in Kaza Wallet dashboard
- Verify webhook URL is accessible (test in browser)
- Check WHMCS gateway logs for callback errors

**Hash Validation Failures**
- Confirm secret key matches in both WHMCS and Kaza Wallet
- Ensure webhook payload format is correct

### Log Files

- WHMCS Gateway Logs: `Admin → Utilities → Logs → Gateway Log`
- System Activity: `Admin → Utilities → Logs → Activity Log`

## API Endpoints

The plugin uses the following Kaza Wallet API endpoints:

- **Live**: `https://api.kazawallet.com`
- **Sandbox**: `https://sandbox-api.kazawallet.com`

### Endpoints Used

- `POST /v1/payment-links` - Create payment link
- `POST /v1/refunds` - Process refunds

## Security Features

- ✅ Hash-based callback verification
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

**Note**: Always test in sandbox mode before enabling live payments.
