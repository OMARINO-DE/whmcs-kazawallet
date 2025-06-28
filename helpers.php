<?php
/**
 * Kaza Wallet Helper Functions
 *
 * @author OMARINO IT Services
 * @copyright Copyright (c) OMARINO IT Services 2025
 * @license Mozilla Public License Version 2.0
 * @version 1.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Validate Kaza Wallet API credentials
 *
 * @param string $apiKey
 * @param string $apiSecret
 * @return array
 */
function validateKazaWalletCredentials($apiKey, $apiSecret)
{
    // Create a test payment link with minimal data to validate credentials
    $testData = array(
        'amount' => '1.00',
        'currency' => 'USD',
        'email' => 'test@example.com',
        'ref' => 'TEST-' . time(),
        'redirectUrl' => 'https://example.com'
    );
    
    $url = 'https://outdoor.kasroad.com/wallet/createPaymentLink';
    $headers = array(
        'x-api-key: ' . $apiKey,
        'Content-Type: application/json',
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return array(
        'success' => $httpCode === 200 || $httpCode === 201,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error
    );
}

/**
 * Generate secure signature for webhook verification
 *
 * @param string $amount
 * @param string $orderId
 * @param string $apiKey
 * @param string $apiSecret
 * @return string
 */
function generateKazaWalletSignature($amount, $orderId, $apiKey, $apiSecret)
{
    // Create the secret string by concatenating the amount, order_id, and apiKey
    $secretString = $amount . ':::' . $orderId . ':::' . $apiKey;
    
    // Generate a SHA-256 hash of the secret string
    $hashDigest = hash('sha256', $secretString, true);
    
    // Generate an HMAC-SHA512 hash of the SHA-256 hash using the apiSecret
    $hmacDigest = hash_hmac('sha512', $hashDigest, $apiSecret, true);
    
    // Encode the HMAC-SHA512 hash in Base64
    return base64_encode($hmacDigest);
}

/**
 * Format currency amount for API
 *
 * @param float $amount
 * @param string $currency
 * @return string
 */
function formatKazaWalletAmount($amount, $currency = 'USD')
{
    // Most currencies use 2 decimal places
    $decimalPlaces = 2;
    
    // Some currencies don't use decimal places
    $noDecimalCurrencies = array('JPY', 'KRW', 'VND', 'CLP');
    if (in_array(strtoupper($currency), $noDecimalCurrencies)) {
        $decimalPlaces = 0;
    }
    
    return number_format($amount, $decimalPlaces, '.', '');
}

/**
 * Get supported currencies
 *
 * @return array
 */
function getKazaWalletSupportedCurrencies()
{
    return array(
        'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'CNY', 'INR', 'BRL',
        'MXN', 'SGD', 'HKD', 'NOK', 'SEK', 'DKK', 'PLN', 'CZK', 'HUF', 'ILS',
        'NZD', 'PHP', 'THB', 'MYR', 'KRW', 'TWD', 'RUB', 'TRY', 'ZAR', 'AED'
    );
}

/**
 * Validate webhook payload
 *
 * @param array $payload
 * @return array
 */
function validateKazaWalletWebhook($payload)
{
    $required = array('order_id', 'secret', 'amount', 'ref', 'status', 'currency');
    $missing = array();
    
    foreach ($required as $field) {
        if (!isset($payload[$field]) || empty($payload[$field])) {
            $missing[] = $field;
        }
    }
    
    return array(
        'valid' => empty($missing),
        'missing_fields' => $missing
    );
}

/**
 * Log Kaza Wallet transaction
 *
 * @param string $gatewayName
 * @param array $data
 * @param string $description
 */
function logKazaWalletTransaction($gatewayName, $data, $description)
{
    if (function_exists('logTransaction')) {
        logTransaction($gatewayName, $data, $description);
    }
}

/**
 * Get payment status message
 *
 * @param string $status
 * @return string
 */
function getKazaWalletStatusMessage($status)
{
    $messages = array(
        'fulfilled' => 'Payment completed successfully',
        'timed_out' => 'Payment link has timed out',
        'pending' => 'Payment is being processed',
        'cancelled' => 'Payment was cancelled'
    );
    
    return isset($messages[$status]) ? $messages[$status] : 'Unknown payment status';
}

/**
 * Clean and validate phone number
 *
 * @param string $phone
 * @return string
 */
function cleanKazaWalletPhone($phone)
{
    // Remove all non-numeric characters except +
    $cleaned = preg_replace('/[^0-9+]/', '', $phone);
    
    // Ensure it starts with + if it's an international number
    if (strlen($cleaned) > 10 && !strpos($cleaned, '+') === 0) {
        $cleaned = '+' . $cleaned;
    }
    
    return $cleaned;
}

/**
 * Generate unique reference ID
 *
 * @param string $invoiceId
 * @return string
 */
function generateKazaWalletReference($invoiceId)
{
    return 'WHMCS-' . $invoiceId . '-' . time();
}

/**
 * Check if currency is supported
 *
 * @param string $currency
 * @return bool
 */
function isKazaWalletCurrencySupported($currency)
{
    return in_array(strtoupper($currency), getKazaWalletSupportedCurrencies());
}
