# Kaza Wallet API Setup Guide

## Prerequisites

1. **Kaza Wallet Merchant Account** - You need an active merchant account
2. **API Credentials** - Obtain your API Key and API Secret from Kaza Wallet
3. **WHMCS Installation** - WHMCS 7.0+ with admin access

## Step 1: Get API Credentials

1. Log into your Kaza Wallet merchant dashboard
2. Navigate to API/Developer settings  
3. Copy your **API Key** (x-api-key)
4. Request your **API Secret** (x-api-secret) from Kaza Wallet support if needed

## Step 2: Install Gateway

1. Upload `kazawallet.php` to `[WHMCS]/modules/gateways/`
2. Upload `callback/kazawallet.php` to `[WHMCS]/modules/gateways/callback/`
3. Set file permissions to 644 or 755

## Step 3: Activate in WHMCS

⚠️ **IMPORTANT**: Follow the correct activation order!

1. **First**: WHMCS Admin → Setup → System Settings → Payment → **Apps & Integrations**
2. **Find**: "Kaza Wallet Payment Gateway" 
3. **Activate**: Click to activate it
4. **Then**: Go to Setup → Payment Gateways
5. **Configure**: Find "Kaza Wallet Payment Gateway" and click "Activate"

## Step 4: Configure Gateway

In the gateway configuration:

| Field | Value |
|-------|-------|
| **API Key** | Your Kaza Wallet API Key (x-api-key) |
| **API Secret** | Your Kaza Wallet API Secret (x-api-secret) |
| **Test Mode** | Check for testing, uncheck for live payments |

## Step 5: Setup Webhook

**Critical Step**: Configure webhook in Kaza Wallet dashboard

1. **Webhook URL**: `https://yourdomain.com/modules/gateways/callback/kazawallet.php`
2. **Copy** this exact URL to your Kaza Wallet merchant dashboard
3. **Configure** the webhook to send payment notifications
4. **Test** the webhook with a small payment

## Step 6: Test Payment

1. **Enable Test Mode** in gateway configuration
2. **Create** a test invoice in WHMCS
3. **Attempt Payment** - you should be redirected to Kaza Wallet
4. **Complete Payment** on Kaza Wallet platform
5. **Verify** invoice is marked as paid in WHMCS

## Payment Flow

```
Customer → WHMCS Invoice → Pay Now Button → 
Kaza Wallet API (create payment link) → 
Customer redirected to Kaza Wallet → 
Customer pays → 
Kaza Wallet webhook → 
WHMCS callback verification → 
Invoice marked as paid
```

## Troubleshooting

### Payment Link Not Created
- Check API Key is correct
- Verify network connectivity to `https://outdoor.kasroad.com`
- Check WHMCS gateway logs for API errors

### Webhook Not Working
- Verify webhook URL is exactly: `https://yourdomain.com/modules/gateways/callback/kazawallet.php`
- Check webhook is configured in Kaza Wallet dashboard
- Verify API Secret is correct
- Check WHMCS gateway logs for signature verification errors

### Refunds Not Working
- Ensure API Secret is configured (required for withdrawal API)
- Check client has complete address information (required for withdrawal)
- Review withdrawal request response in gateway logs

## Security Notes

- **Keep API Secret secure** - Never expose in client-side code
- **Use HTTPS** - Always use SSL for webhook URLs
- **Verify signatures** - All webhooks are verified with HMAC-SHA512
- **Monitor logs** - Check WHMCS gateway logs regularly

## Support

1. **API Issues**: Contact Kaza Wallet support for API-related problems
2. **WHMCS Issues**: Check WHMCS gateway logs and documentation
3. **Integration Issues**: Verify webhook URL and API credentials

---

**Version**: 2.0.0 - Full API Integration
**Last Updated**: June 28, 2025
