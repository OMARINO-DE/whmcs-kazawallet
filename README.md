# WHMCS Kaza Wallet Payment Gateway

A secure, production-ready WHMCS payment gateway plugin for Kaza Wallet payment processing.

**Developed by OMARINO IT Services** - Professional WHMCS Gateway Development

<div align="center">
  <img src="https://www.omarino.de/wp-content/uploads/2024/01/LOGO.png" alt="OMARINO IT Services" height="60">
  <p><strong>OMARINO IT Services</strong><br>
  Website: <a href="https://www.omarino.de">https://www.omarino.de</a><br>
  Support: <a href="mailto:info@omarino.de">info@omarino.de</a></p>
</div>

---

## Features

- ✅ **Real Kaza Wallet API integration** - Direct API connection to Kaza Wallet
- ✅ **Payment link creation** - Secure payment links via Kaza Wallet API
- ✅ **Automatic webhook processing** - Real-time payment notifications
- ✅ **Signature verification** - HMAC-SHA512 security with timing attack prevention
- ✅ **Refund support** - Process refunds directly through the gateway
- ✅ **Admin email override** - Use master email for all transactions
- ✅ **Professional branding** - Clean, branded payment experience
- 🔒 **Enterprise Security** - Comprehensive input validation and sanitization
- 🔒 **SSL/TLS Hardened** - Strict HTTPS verification for all communications
- 🔒 **Rate Limiting** - Webhook protection against abuse
- ✅ **WHMCS-compliant** - Follows all WHMCS payment gateway standards

## Installation

### Step 1: Upload Files
1. Upload `kazawallet.php` to your WHMCS `modules/gateways/` directory
2. Upload `callback/kazawallet.php` to your WHMCS `modules/gateways/callback/` directory

### Step 2: Activate the Gateway
1. **Login to WHMCS Admin**
2. **Go to**: Setup → System Settings → Payment → **Apps & Integrations**
3. **Find**: "Kaza Wallet Payment Gateway" and activate it
4. **Then**: Go to Setup → Payment Gateways 
5. **Find**: "Kaza Wallet Payment Gateway" (it will now appear here)
6. **Click**: "Activate" and configure with your API credentials

**Important**: The gateway MUST be activated in Apps & Integrations first!

## Configuration

Configure these settings in WHMCS Admin → Payment Gateways → Kaza Wallet Payment Gateway:

| Field | Description | Required |
|-------|-------------|----------|
| API Key | Your Kaza Wallet API Key (x-api-key) | ✅ Yes |
| API Secret | Your Kaza Wallet API Secret (x-api-secret) | ✅ Yes |
| Payment Email | Registered Kaza Wallet email address | ✅ Yes |

**Get your API credentials from your Kaza Wallet merchant dashboard.**

### Webhook Configuration

Configure this webhook URL in your Kaza Wallet merchant dashboard:
```
https://yourdomain.com/modules/gateways/callback/kazawallet.php
```
Replace `yourdomain.com` with your actual WHMCS domain.

## Payment Flow

1. **Customer clicks "Pay Now"** → Gateway creates secure payment link
2. **Redirect to Kaza Wallet** → Customer completes payment on Kaza Wallet
3. **Webhook notification** → Kaza Wallet notifies your server of payment status
4. **Automatic processing** → Invoice is automatically marked as paid
5. **Customer redirect** → Customer returns to your site with payment confirmation

## File Structure

```
whmcs-root/
├── modules/gateways/
│   └── kazawallet.php              # Main gateway file
└── modules/gateways/callback/
    └── kazawallet.php              # Webhook callback handler
```

## Requirements

- **WHMCS**: Version 7.0 or higher
- **PHP**: Version 5.6 or higher (7.4+ recommended)
- **SSL Certificate**: HTTPS required for webhook security
- **Kaza Wallet Account**: Valid merchant account with API access

## Troubleshooting

### Gateway Not Appearing?
1. **Setup → System Settings → Payment → Apps & Integrations** (activate here first)
2. **Then** Setup → Payment Gateways (gateway will appear here)

### Common Issues
- **"User not found" error**: Ensure Payment Email is registered with Kaza Wallet
- **File location error**: Verify `kazawallet.php` is in correct `modules/gateways/` directory
- **Webhook issues**: Check that webhook URL is correctly configured in Kaza Wallet dashboard
- **Payment not processed**: Verify API credentials and webhook URL accessibility

### Support Logs
The gateway automatically logs important events to WHMCS system logs for troubleshooting.

## Version History

- **v3.0.0** - 🎉 **PRODUCTION RELEASE**: Complete, tested, and production-ready payment gateway
- **v2.5.3** - ✅ Fully working payment processing with webhook handling  
- **v2.4.6** - 🔧 Enhanced callback URL handling for proper webhook notifications
- **v2.4.5** - 🔄 Fixed return URL handling and webhook processing
- **v2.4.0** - 🔒 Security enhancements and code quality improvements
- **v2.1.0** - 🎉 Working payment gateway with email override functionality
- **v2.0.0** - Full API integration with payment links, webhooks, and refunds
- **v1.0.0** - Initial development release

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

## License

This Source Code Form is subject to the terms of the **Mozilla Public License, v. 2.0**. If a copy of the MPL was not distributed with this file, You can obtain one at [https://mozilla.org/MPL/2.0/](https://mozilla.org/MPL/2.0/).

**Copyright (c) OMARINO IT Services 2025**

---

**✅ PRODUCTION READY v3.0.0**

This gateway is fully tested and ready for live payment processing with the Kaza Wallet API. All debugging and testing files have been removed for clean production deployment.

---
