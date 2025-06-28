<?php
/**
 * Kaza Wallet Gateway Configuration
 * 
 * This file contains configuration constants for the Kaza Wallet payment gateway.
 * Modify these values according to your setup requirements.
 */

// API Configuration
define('KAZAWALLET_API_URL', 'https://outdoor.kasroad.com');

// API Endpoints
define('KAZAWALLET_PAYMENT_LINK_ENDPOINT', '/wallet/createPaymentLink');
define('KAZAWALLET_WITHDRAWAL_ENDPOINT', '/wallet/createWithdrawalRequest');

// Gateway Settings
define('KAZAWALLET_GATEWAY_NAME', 'Kaza Wallet');
define('KAZAWALLET_GATEWAY_VERSION', '1.0.0');
define('KAZAWALLET_DEFAULT_CURRENCY', 'USD');

// Webhook Settings
define('KAZAWALLET_WEBHOOK_TIMEOUT', 30); // seconds
define('KAZAWALLET_HASH_ALGORITHM', 'sha256');
define('KAZAWALLET_HMAC_ALGORITHM', 'sha512');

// Error Messages
define('KAZAWALLET_ERROR_API_KEY', 'Invalid API Key provided');
define('KAZAWALLET_ERROR_PAYMENT_FAILED', 'Payment processing failed');
define('KAZAWALLET_ERROR_INVALID_HASH', 'Invalid webhook signature verification');
define('KAZAWALLET_ERROR_NETWORK', 'Network connection error');

// Success Messages
define('KAZAWALLET_SUCCESS_PAYMENT', 'Payment processed successfully');
define('KAZAWALLET_SUCCESS_REFUND', 'Refund processed successfully');
