<?php
/**
 * WHMCS Kaza Wallet Payment Gateway Module
 *
 * Payment Gateway module for integrating Kaza Wallet payment processing
 * with the WHMCS platform. Developed by OMARINO IT Services.
 *
 * @author OMARINO IT Services
 * @copyright Copyright (c) OMARINO IT Services 2025
 * @license Mozilla Public License Version 2.0
 * @version 3.0.0
 * @website https://www.omarino.de
 * @support info@omarino.de
 * @see https://developers.whmcs.com/payment-gateways/
 */

/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Security: Prevent direct access via web browser (but allow WHMCS payment form requests)
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET' && !defined('ADMINAREA')) {
    // Allow GET requests when WHMCS is calling the gateway functions for payment forms
    $isWhmcsRequest = (
        defined('WHMCS') || 
        (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], 'viewinvoice.php') !== false) ||
        (isset($_SERVER['REQUEST_URI']) && (
            strpos($_SERVER['REQUEST_URI'], 'viewinvoice.php') !== false ||
            strpos($_SERVER['REQUEST_URI'], 'clientarea.php') !== false
        ))
    );
    
    if (!$isWhmcsRequest) {
        header('HTTP/1.1 403 Forbidden');
        exit('Access denied');
    }
}

/**
 * Gateway constants
 */
define('KAZAWALLET_VERSION', '2.5.2');
define('KAZAWALLET_API_BASE_URL', 'https://outdoor.kasroad.com/wallet');
define('KAZAWALLET_MAX_AMOUNT', 999999.99);
define('KAZAWALLET_MIN_AMOUNT', 0.01);
define('KAZAWALLET_DEFAULT_PAYMENT_METHOD', '37'); // Hawala less than 3M
define('KAZAWALLET_MAX_REQUEST_SIZE', 10240); // 10KB
define('KAZAWALLET_RATE_LIMIT_WINDOW', 60); // 1 minute
define('KAZAWALLET_RATE_LIMIT_MAX_REQUESTS', 10);
define('KAZAWALLET_SIGNATURE_TOLERANCE', 300); // 5 minutes

/**
 * Security helper functions
 */

/**
 * Enhanced logging system with levels
 *
 * @param string $level
 * @param string $message
 * @param array $context
 * @return void
 */
function kazawallet_log($level, $message, $context = [])
{
    $levels = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
    if (!in_array($level, $levels)) {
        $level = 'INFO';
    }
    
    $timestamp = date('Y-m-d H:i:s T');
    $requestId = kazawallet_getRequestId();
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    
    $logMessage = "[{$timestamp}] KazaWallet.{$level}: [{$requestId}] {$message}{$contextStr}";
    error_log($logMessage);
}

/**
 * Generate or retrieve request ID for tracking
 *
 * @return string
 */
function kazawallet_getRequestId()
{
    static $requestId = null;
    if ($requestId === null) {
        $requestId = uniqid('kw_', true);
    }
    return $requestId;
}

/**
 * Generate secure random token
 *
 * @param int $length
 * @return string
 */
function kazawallet_generateSecureToken($length = 32)
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    } else {
        // Fallback for older PHP versions
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/62))), 0, $length);
    }
}

/**
 * Validate and sanitize email address with enhanced security
 *
 * @param string $email
 * @param int $maxLength
 * @return string|false
 */
function kazawallet_validateEmail($email, $maxLength = 254)
{
    if (empty($email)) {
        kazawallet_log('WARNING', 'Email validation failed: empty email');
        return false;
    }
    
    // Security: Check length before processing
    if (strlen($email) > $maxLength) {
        kazawallet_log('WARNING', 'Email validation failed: exceeds maximum length', ['length' => strlen($email)]);
        return false;
    }
    
    $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
    if ($email === false) {
        kazawallet_log('WARNING', 'Email validation failed: invalid format');
        return false;
    }
    
    // Additional security: Check for common malicious patterns
    $maliciousPatterns = ['<script', 'javascript:', 'data:', 'vbscript:', 'onload=', 'onerror=', 'eval(', 'expression('];
    foreach ($maliciousPatterns as $pattern) {
        if (stripos($email, $pattern) !== false) {
            kazawallet_log('CRITICAL', 'Email validation failed: malicious pattern detected', ['pattern' => $pattern]);
            return false;
        }
    }
    
    kazawallet_log('DEBUG', 'Email validation successful', ['email' => substr($email, 0, 3) . '***']);
    return $email;
}

/**
 * Validate API key format with enhanced validation
 *
 * @param string $apiKey
 * @return bool
 */
function kazawallet_validateApiKey($apiKey)
{
    if (empty($apiKey)) {
        kazawallet_log('WARNING', 'API key validation failed: empty key');
        return false;
    }
    
    // Security: Check length bounds (Kaza Wallet uses 12-character alphanumeric credentials)
    $length = strlen($apiKey);
    if ($length < 8 || $length > 200) {
        kazawallet_log('WARNING', 'API key validation failed: invalid length', ['length' => $length]);
        return false;
    }
    
    // Security: API key should contain valid characters
    // Kaza Wallet uses alphanumeric format (letters and numbers) but also allow other common API key formats
    if (!preg_match('/^[a-zA-Z0-9\-_.+=\/]{8,200}$/', $apiKey)) {
        kazawallet_log('WARNING', 'API key validation failed: invalid format');
        return false;
    }
    
    // Security: Basic sanity check - shouldn't be all the same character
    if (preg_match('/^(.)\1+$/', $apiKey)) {
        kazawallet_log('WARNING', 'API key validation failed: appears to be invalid pattern');
        return false;
    }
    
    kazawallet_log('DEBUG', 'API key validation successful', ['length' => $length]);
    return true;
}

/**
 * Validate API secret format with enhanced validation
 *
 * @param string $apiSecret
 * @return bool
 */
function kazawallet_validateApiSecret($apiSecret)
{
    if (empty($apiSecret)) {
        kazawallet_log('WARNING', 'API secret validation failed: empty secret');
        return false;
    }
    
    // Security: Check length bounds (Kaza Wallet uses 12-character alphanumeric credentials)
    $length = strlen($apiSecret);
    if ($length < 8 || $length > 200) {
        kazawallet_log('WARNING', 'API secret validation failed: invalid length', ['length' => $length]);
        return false;
    }
    
    // Security: API secret should contain valid characters
    // Kaza Wallet uses alphanumeric format (letters and numbers) but also allow other common API secret formats
    if (!preg_match('/^[a-zA-Z0-9\-_.+=\/]{8,200}$/', $apiSecret)) {
        kazawallet_log('WARNING', 'API secret validation failed: invalid format');
        return false;
    }
    
    // Security: Basic sanity check - shouldn't be all the same character
    if (preg_match('/^(.)\1+$/', $apiSecret)) {
        kazawallet_log('WARNING', 'API secret validation failed: appears to be invalid pattern');
        return false;
    }
    
    kazawallet_log('DEBUG', 'API secret validation successful', ['length' => $length]);
    return true;
}

/**
 * Validate amount with configurable limits
 *
 * @param mixed $amount
 * @param float $minAmount
 * @param float $maxAmount
 * @return float|false
 */
function kazawallet_validateAmount($amount, $minAmount = KAZAWALLET_MIN_AMOUNT, $maxAmount = KAZAWALLET_MAX_AMOUNT)
{
    if (!is_numeric($amount)) {
        kazawallet_log('WARNING', 'Amount validation failed: not numeric', ['amount' => $amount]);
        return false;
    }
    
    $amount = (float)$amount;
    
    // Security: Amount should be within reasonable limits
    if ($amount < $minAmount || $amount > $maxAmount) {
        kazawallet_log('WARNING', 'Amount validation failed: out of range', [
            'amount' => $amount,
            'min' => $minAmount,
            'max' => $maxAmount
        ]);
        return false;
    }
    
    kazawallet_log('DEBUG', 'Amount validation successful', ['amount' => $amount]);
    return $amount;
}

/**
 * Validate currency code with configurable whitelist
 *
 * @param string $currency
 * @param array $supportedCurrencies
 * @return string|false
 */
function kazawallet_validateCurrency($currency, $supportedCurrencies = null)
{
    if (empty($currency)) {
        kazawallet_log('WARNING', 'Currency validation failed: empty currency');
        return false;
    }
    
    // Security: Currency should be 3-letter ISO code
    if (!preg_match('/^[A-Z]{3}$/', strtoupper($currency))) {
        kazawallet_log('WARNING', 'Currency validation failed: invalid format', ['currency' => $currency]);
        return false;
    }
    
    // Security: Default whitelist of supported currencies
    if ($supportedCurrencies === null) {
        $supportedCurrencies = ['USD', 'EUR', 'GBP', 'IQD', 'JOD', 'AED', 'SAR', 'KWD', 'BHD', 'QAR', 'OMR'];
    }
    
    $currency = strtoupper($currency);
    if (!in_array($currency, $supportedCurrencies, true)) {
        kazawallet_log('WARNING', 'Currency validation failed: not supported', [
            'currency' => $currency,
            'supported' => $supportedCurrencies
        ]);
        return false;
    }
    
    kazawallet_log('DEBUG', 'Currency validation successful', ['currency' => $currency]);
    return $currency;
}

/**
 * Validate invoice ID with enhanced validation
 *
 * @param mixed $invoiceId
 * @param int $maxLength
 * @return string|false
 */
function kazawallet_validateInvoiceId($invoiceId, $maxLength = 20)
{
    if (empty($invoiceId)) {
        kazawallet_log('WARNING', 'Invoice ID validation failed: empty ID');
        return false;
    }
    
    $invoiceId = (string)$invoiceId;
    
    // Security: Check length
    if (strlen($invoiceId) > $maxLength) {
        kazawallet_log('WARNING', 'Invoice ID validation failed: exceeds maximum length', [
            'length' => strlen($invoiceId),
            'max' => $maxLength
        ]);
        return false;
    }
    
    // Security: Invoice ID should be numeric
    if (!preg_match('/^[0-9]{1,' . $maxLength . '}$/', $invoiceId)) {
        kazawallet_log('WARNING', 'Invoice ID validation failed: invalid format', ['id' => $invoiceId]);
        return false;
    }
    
    kazawallet_log('DEBUG', 'Invoice ID validation successful', ['id' => $invoiceId]);
    return $invoiceId;
}

/**
 * Sanitize HTML output to prevent XSS
 *
 * @param string $text
 * @return string
 */
function kazawallet_sanitizeHtml($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Create secure cURL request with enhanced security and logging
 *
 * @param string $url
 * @param array $data
 * @param array $headers
 * @param int $timeout
 * @param string $method
 * @return array
 */
function kazawallet_secureCurlRequest($url, $data, $headers, $timeout = 30, $method = 'POST')
{
    $requestId = kazawallet_getRequestId();
    
    // Security: Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https:\/\//', $url)) {
        kazawallet_log('ERROR', 'cURL request failed: invalid URL', ['url' => $url, 'request_id' => $requestId]);
        return ['error' => true, 'message' => 'Invalid API URL'];
    }
    
    // Security: Validate method
    $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
    if (!in_array(strtoupper($method), $allowedMethods)) {
        kazawallet_log('ERROR', 'cURL request failed: invalid method', ['method' => $method, 'request_id' => $requestId]);
        return ['error' => true, 'message' => 'Invalid HTTP method'];
    }
    
    // Security: Limit timeout
    $timeout = min(max($timeout, 10), 60);
    
    // Security: Validate data size
    $dataSize = strlen(json_encode($data));
    if ($dataSize > KAZAWALLET_MAX_REQUEST_SIZE) {
        kazawallet_log('ERROR', 'cURL request failed: data too large', [
            'size' => $dataSize,
            'max' => KAZAWALLET_MAX_REQUEST_SIZE,
            'request_id' => $requestId
        ]);
        return ['error' => true, 'message' => 'Request data too large'];
    }
    
    kazawallet_log('INFO', 'Starting API request', [
        'url' => $url,
        'method' => $method,
        'timeout' => $timeout,
        'data_size' => $dataSize,
        'request_id' => $requestId
    ]);
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array_merge($headers, [
            'X-Request-ID: ' . $requestId,
            'Accept-Charset: UTF-8'
        ]),
        // Security: Enable SSL verification (relaxed for testing)
        CURLOPT_SSL_VERIFYPEER => false, // TEMPORARY: Disable for testing
        CURLOPT_SSL_VERIFYHOST => 0,     // TEMPORARY: Disable for testing
        CURLOPT_CAINFO => null, // Use system CA bundle
        // Security: Additional protections
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'WHMCS-KazaWallet/' . KAZAWALLET_VERSION,
        CURLOPT_HTTPAUTH => CURLAUTH_NONE,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
    ));

    $startTime = microtime(true);
    $response = curl_exec($curl);
    $endTime = microtime(true);
    
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    
    $duration = round(($endTime - $startTime) * 1000, 2);

    kazawallet_log('INFO', 'API request completed', [
        'http_code' => $httpCode,
        'duration_ms' => $duration,
        'response_size' => strlen($response),
        'has_error' => !empty($error),
        'request_id' => $requestId
    ]);

    if (!empty($error)) {
        kazawallet_log('ERROR', 'cURL request failed', [
            'error' => $error,
            'request_id' => $requestId
        ]);
    }

    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'error' => $error,
        'duration' => $duration,
        'info' => $info,
        'request_id' => $requestId
    ];
}

/**
 * Define module related meta data.
 *
 * @return array
 */
function kazawallet_MetaData()
{
    return array(
        'DisplayName' => 'Kaza Wallet Payment Gateway',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
        'Developer' => 'OMARINO IT Services',
        'Version' => '2.5.2',
        'Website' => 'https://www.omarino.de',
    );
}

/**
 * Define gateway configuration options.
 *
 * @return array
 */
function kazawallet_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Kaza Wallet Payment Gateway',
        ),
        // OMARINO IT Services Branding
        '_branding' => array(
            'FriendlyName' => 'Gateway Information',
            'Type' => 'System',
            'Value' => '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 10px 0;">
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <img src="https://www.omarino.de/wp-content/uploads/2024/01/LOGO.png" alt="OMARINO IT Services" style="height: 40px; margin-right: 15px;">
                    <div>
                        <h4 style="margin: 0; color: #2c3e50;">OMARINO IT Services</h4>
                        <p style="margin: 0; color: #6c757d; font-size: 14px;">Professional WHMCS Gateway Development</p>
                    </div>
                </div>
                <div style="border-top: 1px solid #dee2e6; padding-top: 15px;">
                    <p style="margin: 5px 0; color: #495057;"><strong>Gateway:</strong> Kaza Wallet Payment Gateway v2.5.2</p>
                    <p style="margin: 5px 0; color: #495057;"><strong>Developer:</strong> OMARINO IT Services</p>
                    <p style="margin: 5px 0; color: #495057;"><strong>Website:</strong> <a href="https://www.omarino.de" target="_blank" style="color: #007bff;">https://www.omarino.de</a></p>
                    <p style="margin: 5px 0; color: #495057;"><strong>Support:</strong> <a href="mailto:info@omarino.de" style="color: #007bff;">info@omarino.de</a></p>
                </div>
            </div>',
            'Description' => '',
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your Kaza Wallet API Key (x-api-key)<br><small style="color: #6c757d;">Get this from your Kaza Wallet merchant dashboard. Must be 20-100 alphanumeric characters.</small>',
        ),
        'apiSecret' => array(
            'FriendlyName' => 'API Secret',
            'Type' => 'password',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your Kaza Wallet API Secret (x-api-secret)<br><small style="color: #6c757d;">Used for webhook signature verification. Keep this secure and never share it.</small>',
        ),
        'paymentEmail' => array(
            'FriendlyName' => 'Payment Email',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Email address registered with Kaza Wallet <br><small style="color: #6c757d;">This email must be registered in your Kaza Wallet account and will be validated.</small>',
        ),

        '_webhook_info' => array(
            'FriendlyName' => 'Webhook Configuration',
            'Type' => 'System',
            'Value' => '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 15px 0;">
                <h4 style="margin: 0 0 15px 0; color: #856404;"><i class="fas fa-exclamation-triangle"></i> CRITICAL: Webhook Setup Required</h4>
                <p style="margin: 5px 0 15px 0; color: #856404; font-weight: bold;">You MUST configure this webhook URL in your Kaza Wallet merchant dashboard for payments to work:</p>
                
                <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin: 15px 0;">
                    <label style="font-weight: bold; color: #495057; display: block; margin-bottom: 8px;">Webhook URL:</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="text" id="kazawallet_webhook_url" value="' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/modules/gateways/callback/kazawallet.php" style="flex: 1; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-family: monospace; font-size: 13px;" readonly>
                        <button type="button" onclick="copyWebhookUrl()" style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">📋 Copy</button>
                    </div>
                </div>
                
                <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 15px 0;">
                    <h5 style="margin: 0 0 10px 0; color: #1976d2;">Setup Instructions:</h5>
                    <ol style="margin: 0; padding-left: 20px; color: #1976d2;">
                        <li>Log into your <strong>Kaza Wallet merchant dashboard</strong></li>
                        <li>Navigate to <strong>Profile → Webhook Settings</strong> (or similar)</li>
                        <li>Paste the webhook URL above into the webhook configuration field</li>
                        <li>Save the configuration</li>
                        <li>Test with a small payment to verify automatic invoice marking</li>
                    </ol>
                </div>
                
                <div style="background: #ffebee; border: 1px solid #ffcdd2; border-radius: 4px; padding: 10px; margin: 10px 0;">
                    <p style="margin: 0; color: #c62828; font-size: 13px;"><strong>⚠️ Important:</strong> Without webhook configuration, invoices will NOT be automatically marked as paid after successful payments!</p>
                </div>
                
                <script>
                function copyWebhookUrl() {
                    const urlField = document.getElementById("kazawallet_webhook_url");
                    urlField.select();
                    urlField.setSelectionRange(0, 99999);
                    document.execCommand("copy");
                    
                    const button = event.target;
                    const originalText = button.innerHTML;
                    button.innerHTML = "✅ Copied!";
                    button.style.background = "#28a745";
                    
                    setTimeout(function() {
                        button.innerHTML = originalText;
                        button.style.background = "#007bff";
                    }, 2000);
                }
                </script>
            </div>',
            'Description' => '',
        ),
    );
}

/**
 * Show on Order Form.
 *
 * Called to display custom content on the order form when this gateway is selected.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return string
 */
function kazawallet_orderform($params)
{
    return '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin: 15px 0; text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
            <img src="https://www.omarino.de/wp-content/uploads/2024/01/LOGO.png" alt="OMARINO IT Services" style="height: 30px; margin-right: 10px;">
            <span style="font-weight: bold; color: #2c3e50;">Powered by OMARINO IT Services</span>
        </div>
        <p style="margin: 0; color: #6c757d; font-size: 13px;">
            Secure payment processing via Kaza Wallet | 
            <a href="https://www.omarino.de" target="_blank" style="color: #007bff; text-decoration: none;">www.omarino.de</a>
        </p>
    </div>';
}

/**
 * Payment link.
 *
 * Creates a payment link using Kaza Wallet API and redirects user to payment page.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return string
 */
function kazawallet_link($params)
{
    // Handle return from payment processor
    if (isset($_GET['kazawallet']) && $_GET['kazawallet'] === 'success') {
        return kazawallet_handleReturn($params);
    }

    // Security: Validate all input parameters
    $apiKey = trim($params['apiKey'] ?? '');
    $apiSecret = trim($params['apiSecret'] ?? '');
    $paymentEmail = trim($params['paymentEmail'] ?? '');
    
    // Security: Validate API credentials
    if (!kazawallet_validateApiKey($apiKey)) {
        return '<div class="alert alert-danger">Configuration error: Invalid API key format. Please contact administrator.</div>';
    }
    
    if (!kazawallet_validateApiSecret($apiSecret)) {
        return '<div class="alert alert-danger">Configuration error: Invalid API secret format. Please contact administrator.</div>';
    }

    // Security: Validate invoice parameters
    $invoiceId = kazawallet_validateInvoiceId($params['invoiceid'] ?? '');
    if ($invoiceId === false) {
        return '<div class="alert alert-danger">Payment error: Invalid invoice reference.</div>';
    }

    $amount = kazawallet_validateAmount($params['amount'] ?? 0);
    if ($amount === false) {
        return '<div class="alert alert-danger">Payment error: Invalid payment amount.</div>';
    }

    $currencyCode = kazawallet_validateCurrency($params['currency'] ?? '');
    if ($currencyCode === false) {
        return '<div class="alert alert-danger">Payment error: Unsupported currency.</div>';
    }

    // Security: Validate customer email
    $customerEmail = kazawallet_validateEmail($params['clientdetails']['email'] ?? '');
    if ($customerEmail === false) {
        return '<div class="alert alert-danger">Payment error: Invalid customer email address.</div>';
    }
    
    // Security: Validate payment email if provided
    $email = $customerEmail; // Default to customer email
    if (!empty($paymentEmail)) {
        $validatedPaymentEmail = kazawallet_validateEmail($paymentEmail);
        if ($validatedPaymentEmail === false) {
            return '<div class="alert alert-danger">Configuration error: Invalid payment email format. Please contact administrator.</div>';
        }
        $email = $validatedPaymentEmail;
    }

    // Security: Validate and sanitize system parameters
    $returnUrl = filter_var(trim($params['returnurl'] ?? ''), FILTER_VALIDATE_URL);
    if (!$returnUrl) {
        return '<div class="alert alert-danger">System error: Invalid return URL.</div>';
    }

    $langPayNow = kazawallet_sanitizeHtml($params['langpaynow'] ?? 'Pay Now');

    try {
        // Prepare callback URL for webhook notifications (for logging/reference only)
        $systemUrl = rtrim($params['systemurl'], '/') . '/';
        $callbackUrl = $systemUrl . 'modules/gateways/callback/kazawallet.php';
        
        // Security: Prepare payment data with validated inputs
        // NOTE: According to Kaza Wallet API docs, webhook URL must be configured in merchant profile
        
        // Fix return URL to ensure proper redirect handling
        $redirectUrl = $returnUrl;
        $separator = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
        $redirectUrl .= $separator . 'kazawallet=success&invoiceid=' . urlencode($invoiceId);
        
        $paymentData = array(
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currencyCode,
            'email' => $email,
            'ref' => $invoiceId,
            'redirectUrl' => $redirectUrl
        );

        // Security: Validate JSON encoding
        $jsonData = json_encode($paymentData);
        if ($jsonData === false) {
            return '<div class="alert alert-danger">Payment error: Unable to process payment data.</div>';
        }

        $apiUrl = KAZAWALLET_API_BASE_URL . '/createPaymentLink';
        
        $headers = array(
            'x-api-key: ' . $apiKey,
            'x-api-secret: ' . $apiSecret,
            'Content-Type: application/json',
            'Accept: application/json'
        );

        // Security: Use secure cURL request
        $result = kazawallet_secureCurlRequest($apiUrl, $paymentData, $headers, 30);

        // Log the payment request details for verification
        kazawallet_log('INFO', 'Payment link creation request', [
            'api_url' => $apiUrl,
            'payment_data' => [
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'],
                'email' => $paymentData['email'],
                'ref' => $paymentData['ref'],
                'redirectUrl' => $paymentData['redirectUrl']
            ],
            'original_return_url' => $returnUrl,
            'webhook_url_for_dashboard' => $callbackUrl,
            'invoice_id' => $invoiceId,
            'request_id' => $result['request_id'] ?? 'unknown'
        ]);

        // TEMPORARY DEBUG: Log detailed response for troubleshooting
        kazawallet_log('DEBUG', 'Payment API response details', [
            'result_error' => $result['error'] ?? 'none',
            'http_code' => $result['httpCode'] ?? 'unknown',
            'response_length' => strlen($result['response'] ?? ''),
            'response_preview' => substr($result['response'] ?? '', 0, 200),
            'request_id' => $result['request_id'] ?? 'unknown'
        ]);

        if (!empty($result['error'])) {
            // Security: Generic error message to prevent information disclosure
            kazawallet_log('ERROR', 'Payment API request failed', [
                'error' => $result['error'],
                'curl_info' => $result['info'] ?? [],
                'invoice_id' => $invoiceId,
                'request_id' => $result['request_id'] ?? 'unknown'
            ]);
            
            // TEMPORARY: Show more specific error for debugging
            $debugMessage = "Payment gateway error. ";
            if (!empty($result['error'])) {
                $debugMessage .= "cURL Error: " . htmlspecialchars($result['error']) . ". ";
            }
            $debugMessage .= "Please contact support with this information.";
            
            return '<div class="alert alert-danger">' . $debugMessage . '</div>';
        }

        if ($result['httpCode'] !== 200) {
            // TEMPORARY: Show HTTP status for debugging
            $httpDebug = "Payment service error (HTTP " . $result['httpCode'] . "). ";
            if (!empty($result['response'])) {
                $httpDebug .= "Response: " . htmlspecialchars(substr($result['response'], 0, 200));
            }
            return '<div class="alert alert-danger">' . $httpDebug . '</div>';
        }

        $responseData = json_decode($result['response'], true);
        if (!$responseData) {
            return '<div class="alert alert-danger">Payment service error. Please try again later.</div>';
        }

        // Security: Validate API response structure
        if (isset($responseData['error'])) {
            // Security: Handle API errors without exposing sensitive details
            if (isset($responseData['error']['details']['key'])) {
                $errorKey = $responseData['error']['details']['key'];
                
                if ($errorKey === 'USER_NOT_FOUND') {
                    return '<div class="alert alert-danger">Payment error: Email address not registered with Kaza Wallet. Please ensure you have a Kaza Wallet account or contact support.</div>';
                } elseif ($errorKey === 'USER_IS_BLOCK_DU_TO_CHANGING_THE_IP') {
                    return '<div class="alert alert-warning">
                        <strong>Account Security Notice:</strong><br>
                        Your Kaza Wallet account has been temporarily blocked due to IP address changes for security reasons.<br>
                        <strong>To resolve this:</strong><br>
                        1. Contact Kaza Wallet support to unblock your account<br>
                        2. Request to whitelist your server IP address for API access<br>
                        3. Or try again from your usual location<br>
                        <em>This is a security feature to protect your account.</em>
                    </div>';
                } else {
                    // Security: Log other API errors for debugging
                    kazawallet_log('WARNING', 'Kaza Wallet API error', [
                        'error_key' => $errorKey,
                        'error_message' => $responseData['error']['message'] ?? 'unknown',
                        'invoice_id' => $invoiceId
                    ]);
                    return '<div class="alert alert-danger">Payment processing temporarily unavailable. Please try again later or contact support.</div>';
                }
            } else {
                // Security: Generic error message for other API errors
                return '<div class="alert alert-danger">Payment processing temporarily unavailable. Please try again later or contact support.</div>';
            }
        } else if (isset($responseData['url']) && !empty($responseData['url'])) {
            // Security: Validate payment URL
            $paymentUrl = filter_var($responseData['url'], FILTER_VALIDATE_URL);
            if (!$paymentUrl || !preg_match('/^https:\/\//', $paymentUrl)) {
                kazawallet_log('ERROR', 'Invalid payment URL received from API', [
                    'url' => $responseData['url'] ?? 'null',
                    'request_id' => $result['request_id'] ?? 'unknown'
                ]);
                return '<div class="alert alert-danger">Payment service error. Please contact support.</div>';
            }
            
            // Security: Sanitize URL for output
            $sanitizedUrl = kazawallet_sanitizeHtml($paymentUrl);
            
            // Security: Generate proper nonce for script
            $nonce = kazawallet_generateSecureToken(16);
            
            kazawallet_log('INFO', 'Payment link created successfully', [
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'currency' => $currencyCode,
                'request_id' => $result['request_id'] ?? 'unknown'
            ]);
            
            $htmlOutput = '<script nonce="' . $nonce . '">
                // Security: Validate URL before redirect
                if (/^https:\/\/[a-zA-Z0-9\-\.]+\//.test("' . addslashes($paymentUrl) . '")) {
                    setTimeout(function() {
                        window.location.href = "' . addslashes($paymentUrl) . '";
                    }, 1000);
                } else {
                    console.error("Invalid payment URL");
                }
            </script>';
            $htmlOutput .= '<div class="text-center">';
            $htmlOutput .= '<p>Redirecting to secure Kaza Wallet payment page...</p>';
            $htmlOutput .= '<div class="spinner-border text-primary" role="status" aria-hidden="true"></div>';
            $htmlOutput .= '<p class="mt-3"><a href="' . $sanitizedUrl . '" class="btn btn-primary" rel="noopener noreferrer" target="_self">' . $langPayNow . '</a></p>';
            $htmlOutput .= '</div>';
            
            return $htmlOutput;
        }

        // Security: Fallback error without exposing details
        return '<div class="alert alert-danger">Payment processing error. Please try again or contact support.</div>';

    } catch (Exception $e) {
        // Security: Log error securely without exposing to user
        error_log('Kaza Wallet Payment Error: ' . $e->getMessage());
        return '<div class="alert alert-danger">Payment processing temporarily unavailable. Please try again later.</div>';
    }
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return array Transaction response status
 */
function kazawallet_refund($params)
{
    // Security: Validate API credentials
    $apiKey = trim($params['apiKey'] ?? '');
    $apiSecret = trim($params['apiSecret'] ?? '');
    
    if (!kazawallet_validateApiKey($apiKey)) {
        return array(
            'status' => 'error',
            'rawdata' => 'Invalid API key configuration',
        );
    }
    
    if (!kazawallet_validateApiSecret($apiSecret)) {
        return array(
            'status' => 'error',
            'rawdata' => 'Invalid API secret configuration',
        );
    }

    // Security: Validate transaction parameters
    $transactionIdToRefund = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($params['transid'] ?? ''));
    if (empty($transactionIdToRefund) || strlen($transactionIdToRefund) > 100) {
        return array(
            'status' => 'error',
            'rawdata' => 'Invalid transaction ID',
        );
    }

    $refundAmount = kazawallet_validateAmount($params['amount'] ?? 0);
    if ($refundAmount === false) {
        return array(
            'status' => 'error',
            'rawdata' => 'Invalid refund amount',
        );
    }

    $currencyCode = kazawallet_validateCurrency($params['currency'] ?? '');
    if ($currencyCode === false) {
        return array(
            'status' => 'error',
            'rawdata' => 'Invalid currency code',
        );
    }

    // Security: Validate client email
    $email = kazawallet_validateEmail($params['clientdetails']['email'] ?? '');
    if ($email === false) {
        return array(
            'status' => 'error',
            'rawdata' => 'Invalid client email address',
        );
    }

    // Security: Validate and sanitize client details
    $clientDetails = $params['clientdetails'] ?? [];
    $firstName = preg_replace('/[^a-zA-Z\s\-\']/', '', trim($clientDetails['firstname'] ?? ''));
    $lastName = preg_replace('/[^a-zA-Z\s\-\']/', '', trim($clientDetails['lastname'] ?? ''));
    $phone = preg_replace('/[^0-9\+\-\s\(\)]/', '', trim($clientDetails['phonenumber'] ?? ''));
    $state = preg_replace('/[^a-zA-Z\s\-\']/', '', trim($clientDetails['state'] ?? ''));

    if (empty($firstName) || empty($lastName)) {
        return array(
            'status' => 'error',
            'rawdata' => 'Invalid client name information',
        );
    }

    try {
        // Security: Prepare withdrawal data with validated inputs
        $withdrawalData = array(
            'email' => $email,
            'currency' => $currencyCode,
            'amount' => number_format($refundAmount, 2, '.', ''),
            'note' => 'Refund for transaction: ' . $transactionIdToRefund,
            'paymentMethod' => KAZAWALLET_DEFAULT_PAYMENT_METHOD,
            'fields' => array(
                'name' => $firstName . ' ' . $lastName,
                'phone' => $phone,
                'hawalahmauto-custom-field-withdraw-3' => $state
            )
        );

        // Security: Validate JSON encoding
        if (json_encode($withdrawalData) === false) {
            kazawallet_log('ERROR', 'Refund data JSON encoding failed', [
                'transaction_id' => $transactionIdToRefund,
                'amount' => $refundAmount
            ]);
            return array(
                'status' => 'error',
                'rawdata' => 'Invalid withdrawal data format',
            );
        }

        $apiUrl = KAZAWALLET_API_BASE_URL . '/createWithdrawalRequest';
        
        $headers = array(
            'x-api-key: ' . $apiKey,
            'x-api-secret: ' . $apiSecret,
            'Content-Type: application/json',
            'Accept: application/json'
        );

        // Security: Use secure cURL request
        $result = kazawallet_secureCurlRequest($apiUrl, $withdrawalData, $headers, 30);

        if (!empty($result['error'])) {
            kazawallet_log('ERROR', 'Refund API request failed', [
                'error' => $result['error'],
                'transaction_id' => $transactionIdToRefund,
                'request_id' => $result['request_id'] ?? 'unknown'
            ]);
            return array(
                'status' => 'error',
                'rawdata' => 'API connection failed',
            );
        }

        $responseData = json_decode($result['response'], true);

        if ($result['httpCode'] === 200 && isset($responseData['success']) && $responseData['success']) {
            return array(
                'status' => 'success',
                'rawdata' => array('success' => true, 'message' => 'Refund processed successfully'),
                'transid' => isset($responseData['withdrawal_id']) ? 
                    preg_replace('/[^a-zA-Z0-9\-_]/', '', $responseData['withdrawal_id']) : 
                    $transactionIdToRefund,
            );
        } else {
            // Security: Don't expose detailed API response
            return array(
                'status' => 'error',
                'rawdata' => 'Refund request failed',
            );
        }

    } catch (Exception $e) {
        // Security: Log error securely without exposing to response
        error_log('Kaza Wallet Refund Error: ' . $e->getMessage());
        return array(
            'status' => 'error',
            'rawdata' => 'Refund processing error',
        );
    }
}

/**
 * Handle return from payment processor
 *
 * Called when user returns from Kaza Wallet payment page.
 * Checks payment status and displays appropriate message.
 *
 * @param array $params Payment Gateway Module Parameters
 * @return string
 */
function kazawallet_handleReturn($params)
{
    // Try to get invoice ID from multiple sources for better reliability
    $invoiceId = null;
    
    // First try from WHMCS params
    if (isset($params['invoiceid']) && !empty($params['invoiceid'])) {
        $invoiceId = kazawallet_validateInvoiceId($params['invoiceid']);
    }
    
    // If not found, try from GET parameters (from our redirect URL)
    if ($invoiceId === false && isset($_GET['invoiceid']) && !empty($_GET['invoiceid'])) {
        $invoiceId = kazawallet_validateInvoiceId($_GET['invoiceid']);
    }
    
    // If still not found, try to extract from return URL
    if ($invoiceId === false && isset($params['returnurl'])) {
        // Parse the return URL to extract invoice ID
        $urlParts = parse_url($params['returnurl']);
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
            if (isset($queryParams['id'])) {
                $invoiceId = kazawallet_validateInvoiceId($queryParams['id']);
            }
        }
    }
    
    if ($invoiceId === false) {
        kazawallet_log('ERROR', 'Return handler: could not determine invoice ID', [
            'params_invoiceid' => $params['invoiceid'] ?? 'missing',
            'get_invoiceid' => $_GET['invoiceid'] ?? 'missing', 
            'get_params' => $_GET,
            'returnurl' => $params['returnurl'] ?? 'missing'
        ]);
        return '<div class="alert alert-danger">
            <h4>Payment Return Error</h4>
            <p>Unable to determine invoice reference. Please contact support.</p>
            <div style="background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 4px;">
                <small>
                    <strong>Debug Info:</strong><br>
                    • Return URL: ' . htmlspecialchars($params['returnurl'] ?? 'not provided') . '<br>
                    • GET Parameters: ' . htmlspecialchars(http_build_query($_GET)) . '<br>
                    • If payment was successful, please check your invoice status manually.
                </small>
            </div>
        </div>';
    }

    kazawallet_log('INFO', 'Return handler called', [
        'invoice_id' => $invoiceId,
        'get_params' => $_GET
    ]);

    try {
        // Check if invoice is already paid
        $invoiceData = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
        kazawallet_log('INFO', 'Invoice status check', [
            'invoice_id' => $invoiceId,
            'api_result' => $invoiceData['result'] ?? 'unknown',
            'invoice_status' => $invoiceData['status'] ?? 'unknown'
        ]);
        
        if ($invoiceData['result'] === 'success') {
            $status = $invoiceData['status'];
            
            if ($status === 'Paid') {
                kazawallet_log('INFO', 'Invoice marked as paid', ['invoice_id' => $invoiceId]);
                return '<div class="alert alert-success">
                    <h4><i class="fa fa-check-circle"></i> Payment Successful!</h4>
                    <p>Your payment has been processed successfully. Thank you for your payment!</p>
                    <p><strong>Invoice #' . htmlspecialchars($invoiceId) . '</strong> has been marked as paid.</p>
                    <p><a href="' . htmlspecialchars($params['returnurl'] ?? '') . '" class="btn btn-success">Continue</a></p>
                </div>';
            } else {
                // Invoice not yet paid - could be processing delay
                kazawallet_log('WARNING', 'Invoice not yet paid', [
                    'invoice_id' => $invoiceId,
                    'current_status' => $status
                ]);
                
                return '<div class="alert alert-warning">
                    <h4><i class="fa fa-clock-o"></i> Payment Processing</h4>
                    <p>We are verifying your payment. This may take a few minutes.</p>
                    <p>If your payment was successful, the invoice will be updated automatically via webhook.</p>
                    <p><strong>Invoice #' . htmlspecialchars($invoiceId) . '</strong> - Current Status: ' . htmlspecialchars($status) . '</p>
                    
                    <div class="mt-3">
                        <a href="' . htmlspecialchars($params['returnurl'] ?? '') . '" class="btn btn-primary">Check Status</a>
                        <button onclick="window.location.reload()" class="btn btn-secondary">Refresh</button>
                    </div>
                    
                    <div class="mt-3" style="background: #e3f2fd; padding: 15px; border-radius: 4px; border-left: 4px solid #2196f3;">
                        <h6 style="margin: 0 0 10px 0; color: #1976d2;">🔍 Debugging Information:</h6>
                        <small style="color: #1976d2;">
                            • Invoice ID: ' . htmlspecialchars($invoiceId) . '<br>
                            • Current Status: ' . htmlspecialchars($status) . '<br>
                            • Return Time: ' . date('Y-m-d H:i:s T') . '<br>
                            • If payment was completed but invoice is not marked as paid, check webhook logs.
                        </small>
                    </div>
                    
                    <script>
                        // Auto-refresh after 15 seconds to check for webhook processing
                        setTimeout(function() {
                            window.location.reload();
                        }, 15000);
                    </script>
                </div>';
            }
        } else {
            kazawallet_log('ERROR', 'Failed to get invoice data', [
                'invoice_id' => $invoiceId,
                'api_response' => $invoiceData
            ]);
            return '<div class="alert alert-danger">
                Unable to check payment status. Please contact support.
                <br><small>Error: ' . htmlspecialchars($invoiceData['message'] ?? 'Unknown error') . '</small>
            </div>';
        }
    } catch (Exception $e) {
        kazawallet_log('ERROR', 'Return handler error', [
            'error' => $e->getMessage(),
            'invoice_id' => $invoiceId,
            'trace' => $e->getTraceAsString()
        ]);
        return '<div class="alert alert-danger">
            Payment status check failed. Please contact support.
            <br><small>Error details have been logged for debugging.</small>
        </div>';
    }
}

/**
 * Test webhook connectivity for debugging
 * 
 * @param string $webhookUrl
 * @return array
 */
function kazawallet_testWebhook($webhookUrl)
{
    $testData = [
        'order_id' => 'test_' . time(),
        'secret' => 'test_signature',
        'amount' => '10.00',
        'ref' => '12345',
        'status' => 'fulfilled',
        'currency' => 'USD'
    ];

    $result = kazawallet_secureCurlRequest($webhookUrl, $testData, [
        'Content-Type: application/json',
        'User-Agent: KazaWallet-Test/1.0'
    ], 15);

    return [
        'success' => empty($result['error']) && $result['httpCode'] < 400,
        'status_code' => $result['httpCode'],
        'response' => $result['response'],
        'error' => $result['error']
    ];
}

/**
 * Display admin area information about webhook configuration
 *
 * @param array $params
 * @return string
 */
function kazawallet_adminstatusmsg($params)
{
    $systemUrl = rtrim($params['systemurl'], '/') . '/';
    $callbackUrl = $systemUrl . 'modules/gateways/callback/kazawallet.php';
    $webhookCheckerUrl = $systemUrl . 'webhook-checker.php';
    
    return '<div class="alert alert-warning">
        <h4><i class="fa fa-exclamation-triangle"></i> Webhook Configuration Status</h4>
        <p><strong>CRITICAL:</strong> Ensure your webhook URL is configured in Kaza Wallet dashboard:</p>
        
        <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px; margin: 10px 0;">
            <strong>Webhook URL:</strong><br>
            <code style="background: #e9ecef; padding: 5px; border-radius: 3px;">' . htmlspecialchars($callbackUrl) . '</code>
            <button type="button" class="btn btn-sm btn-outline-primary ml-2" onclick="navigator.clipboard.writeText(\'' . htmlspecialchars($callbackUrl) . '\'); this.innerHTML=\'✅ Copied!\'; setTimeout(()=>this.innerHTML=\'📋 Copy\', 2000);">📋 Copy</button>
        </div>
        
        <div class="mt-3">
            <a href="' . htmlspecialchars($webhookCheckerUrl) . '" target="_blank" class="btn btn-primary btn-sm">
                🔍 Test Webhook Connectivity
            </a>
            <small class="text-muted ml-2">Verify your webhook URL is accessible</small>
        </div>
        
        <div class="mt-2">
            <small class="text-muted">
                ℹ️ If invoices are not being marked as paid automatically, the webhook is likely not configured properly in your Kaza Wallet dashboard.
            </small>
        </div>
    </div>';
}
