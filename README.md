# WHMCS Kaza Wallet Payment Gateway

A production-ready WHMCS payment gateway plugin for Kaza Wallet payment processing.

**Developed by OMARINO IT Services** - Professional WHMCS Gateway Development

<div align="center">
  <img src="https://www.omarino.de/wp-content/uploads/2024/01/LOGO.png" alt="OMARINO IT Services" height="60">
  <p><strong>OMARINO IT Services</strong><br>
  Website: <a href="https://www.omarino.de">https://www.omarino.de</a><br>
  Support: <a href="mailto:info@omarino.de">info@omarino.de</a></p>
</div>

---

## Features

- ✅ **Real Kaza Wallet API integration** - Uses actual Kaza Wallet endpoints
- ✅ **Payment link creation** - Creates payment links via Kaza Wallet API
- ✅ **Webhook processing** - Handles payment notifications automatically
- ✅ **Signature verification** - Secure HMAC-SHA512 verification
- ✅ **Refund support** - Processes refunds via withdrawal requests
- ✅ **Admin email override** - Configure a master email for all payments
- ✅ **Order form branding** - Professional OMARINO IT Services branding display
- ✅ **Production ready** - No test mode, live environment only
- ✅ **WHMCS-compliant** - Follows all WHMCS standards
- ✅ **Third-party gateway** - Redirects users to Kaza Wallet payment page

## Installation

### Step 1: Upload Files
1. Upload `kazawallet.php` to your WHMCS `modules/gateways/` directory
2. Upload `callback/kazawallet.php` to your WHMCS `modules/gateways/callback/` directory

### Step 2: Activate the Gateway
1. **Login to WHMCS Admin**
2. **Go to**: Setup → System Settings → Payment → **Apps & Integrations**
3. **Find**: "Kaza Wallet Payment Gateway" and activate it there
4. **Then**: Go to Setup → Payment Gateways 
5. **Find**: "Kaza Wallet Payment Gateway" (it will now appear here)
6. **Click**: "Activate" and configure with your API credentials

**Important**: The gateway MUST be activated in Apps & Integrations first!

## Configuration

| Field | Description |
|-------|-------------|
| API Key | Your Kaza Wallet API Key (x-api-key) |
| API Secret | Your Kaza Wallet API Secret (x-api-secret) |
| Payment Email | Registered Kaza Wallet email (overrides customer emails) |
| Test Mode | Enable for testing |

**Get your API credentials from your Kaza Wallet merchant dashboard.**

## File Structure

```
whmcs-root/
├── modules/gateways/
│   └── kazawallet.php              # Main gateway (REQUIRED)
└── modules/gateways/callback/
    └── kazawallet.php              # Callback handler (REQUIRED)
```

## Repository Structure

```
whmcs-kazawallet/
├── kazawallet.php                  # Main gateway file
├── callback/
│   └── kazawallet.php              # Webhook callback handler
├── README.md                       # This documentation
├── CHANGELOG.md                    # Version history
└── LICENSE                         # License file
```

## Payment Flow

1. Customer clicks "Pay Now" 
2. Gateway creates payment link via Kaza Wallet API
3. Customer redirected to Kaza Wallet payment page
4. After payment, Kaza Wallet sends webhook to your site
5. Callback verifies signature and marks invoice as paid

## Webhook Configuration

Configure this webhook URL in your Kaza Wallet merchant dashboard:
```
https://yourdomain.com/modules/gateways/callback/kazawallet.php
```

## Requirements

- WHMCS 7.0 or higher
- PHP 5.6 or higher
- Valid Kaza Wallet merchant account
- Registered email address in Kaza Wallet system

## Important Notes

- **Email Registration**: The Payment Email field should contain an email address that's registered with Kaza Wallet
- **Production Ready**: This gateway is ready for live payments with proper API credentials
- **Secure Processing**: Uses HMAC-SHA512 signature verification for webhooks
- **Automatic Updates**: Invoice status is updated automatically via webhooks

## Troubleshooting

### Gateway Not Appearing?
1. **Setup → System Settings → Payment → Apps & Integrations** (activate here first)
2. **Then** Setup → Payment Gateways (gateway will appear here)

### Common Issues
- **User not found error**: Configure Payment Email with a registered Kaza Wallet email
- **File location**: Ensure `kazawallet.php` is in `modules/gateways/`
- **WHMCS cache**: Clear template cache if needed

## Version History

- **v2.1.0** - 🎉 PRODUCTION READY: Working payment gateway with email override
- **v2.0.0** - Full API integration with payment links, webhooks, and refunds
- **v1.0.0** - Initial release

## Support & Development

<div align="center">
  <img src="https://www.omarino.de/wp-content/uploads/2024/01/LOGO.png" alt="OMARINO IT Services" height="50">
  
  **Professional WHMCS Gateway Development**
  
  **OMARINO IT Services**  
  🌐 Website: [https://www.omarino.de](https://www.omarino.de)  
  📧 Support: [info@omarino.de](mailto:info@omarino.de)  
  💬 Professional WHMCS development and customization services  
</div>

### Need Custom Gateway Development?

OMARINO IT Services specializes in:
- ✅ Custom WHMCS Payment Gateway Development
- ✅ Payment Provider API Integration
- ✅ WHMCS Module Development
- ✅ E-commerce Solutions
- ✅ Professional Support & Maintenance

Contact us for your next WHMCS project!

---

**✅ Ready for production use!**
