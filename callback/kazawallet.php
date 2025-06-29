<?php
/**
 * WHMCS Kaza Wallet Payment Gateway Callback Handler
 *
 * This file handles payment notifications (webhooks) from Kaza Wallet
 * and processes them within WHMCS. Developed by OMARINO IT Services.
 *
 * IMPORTANT: This file MUST have the same name as the main gateway module
 * (kazawallet.php) for WHMCS to properly route webhook calls.
 *
 * @author OMARINO IT Services
 * @copyright Copyright (c) OMARINO IT Services 2025
 * @license Mozilla Public License Version 2.0
 * @version 3.0.0
 * @website https://www.omarino.de
 * @support info@omarino.de
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 */

/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Security: Allow POST requests for webhooks (but verify they're legitimate)
if (!isset($_SERVER['REQUEST_METHOD'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid Request');
}

// Security: Only allow POST requests for webhooks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    exit('Method Not Allowed');
}

// Security: Rate limiting - simple IP-based rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$rateLimitFile = sys_get_temp_dir() . '/kaza_webhook_' . md5($clientIp);
$rateLimitWindow = 60; // 1 minute
$maxRequests = 10; // Max 10 requests per minute

if (file_exists($rateLimitFile)) {
    $requestData = json_decode(file_get_contents($rateLimitFile), true);
    if ($requestData && isset($requestData['timestamp'], $requestData['count'])) {
        if (time() - $requestData['timestamp'] < $rateLimitWindow) {
            if ($requestData['count'] >= $maxRequests) {
                header('HTTP/1.1 429 Too Many Requests');
                exit('Rate limit exceeded');
            }
            $requestData['count']++;
        } else {
            $requestData = ['timestamp' => time(), 'count' => 1];
        }
    } else {
        $requestData = ['timestamp' => time(), 'count' => 1];
    }
} else {
    $requestData = ['timestamp' => time(), 'count' => 1];
}
file_put_contents($rateLimitFile, json_encode($requestData));

// Security: Get and validate raw POST data first
$rawData = file_get_contents('php://input');

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    header('HTTP/1.1 503 Service Unavailable');
    die("Module Not Activated");
}
if (empty($rawData) || strlen($rawData) > 10240) { // Max 10KB
    header('HTTP/1.1 400 Bad Request');
    die("Invalid request data");
}

// Security: Validate JSON structure
$webhookData = json_decode($rawData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // Fallback to POST data with security validation
    $webhookData = array_map(function($value) {
        return is_string($value) ? filter_var($value, FILTER_SANITIZE_STRING) : $value;
    }, $_POST);
}

if (empty($webhookData)) {
    header('HTTP/1.1 400 Bad Request');
    die("No webhook data received");
}

// Security: Validate required webhook fields - handle both 'order_id' and 'id' fields
$requiredFields = ['secret', 'amount', 'ref', 'status', 'currency'];
foreach ($requiredFields as $field) {
    if (!isset($webhookData[$field]) || empty($webhookData[$field])) {
        header('HTTP/1.1 400 Bad Request');
        die("Missing required field: $field");
    }
}

// Handle order_id vs id field name difference
if (!isset($webhookData['order_id']) && !isset($webhookData['id'])) {
    header('HTTP/1.1 400 Bad Request');
    die("Missing required field: order_id or id");
}

// Use either order_id or id field
$orderId = isset($webhookData['order_id']) ? $webhookData['order_id'] : $webhookData['id'];

// Security: Extract and validate webhook data
$orderId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $orderId);
$secret = trim($webhookData['secret']);
$amount = filter_var($webhookData['amount'], FILTER_VALIDATE_FLOAT);
$ref = preg_replace('/[^0-9]/', '', $webhookData['ref']); // Invoice ID should be numeric
$status = preg_replace('/[^a-zA-Z]/', '', strtolower($webhookData['status']));
$currency = preg_replace('/[^A-Z]/', '', strtoupper($webhookData['currency']));

// Security: Validate extracted data
if (empty($orderId) || strlen($orderId) > 100) {
    header('HTTP/1.1 400 Bad Request');
    die("Invalid order ID");
}

if (empty($secret) || strlen($secret) > 500) {
    header('HTTP/1.1 400 Bad Request');
    die("Invalid secret");
}

if ($amount === false || $amount <= 0 || $amount > 999999.99) {
    header('HTTP/1.1 400 Bad Request');
    die("Invalid amount");
}

if (empty($ref) || strlen($ref) > 20) {
    header('HTTP/1.1 400 Bad Request');
    die("Invalid reference");
}

if (!in_array($status, ['fulfilled', 'pending', 'failed', 'cancelled'], true)) {
    header('HTTP/1.1 400 Bad Request');
    die("Invalid status");
}

if (strlen($currency) !== 3) {
    header('HTTP/1.1 400 Bad Request');
    die("Invalid currency");
}

// Use ref as invoice ID (as per our implementation)
$invoiceId = $ref;
$transactionId = $orderId;
$paymentAmount = $amount;

// Determine transaction status
$transactionStatus = ($status === 'fulfilled') ? 'Success' : 'Failure';

/**
 * Security: Enhanced signature validation function
 */
function calculateExpectedSignature($amount, $orderId, $apiKey, $apiSecret)
{
    // Security: Validate inputs
    if (empty($amount) || empty($orderId) || empty($apiKey) || empty($apiSecret)) {
        return false;
    }
    
    try {
        // Create the secret string by concatenating the amount, order_id, and apiKey
        $secretString = number_format((float)$amount, 2, '.', '') . ':::' . $orderId . ':::' . $apiKey;
        
        // Generate a SHA-256 hash of the secret string
        $hashDigest = hash('sha256', $secretString, true);
        if ($hashDigest === false) {
            return false;
        }
        
        // Generate an HMAC-SHA512 hash of the SHA-256 hash using the apiSecret
        $hmacDigest = hash_hmac('sha512', $hashDigest, $apiSecret, true);
        if ($hmacDigest === false) {
            return false;
        }
        
        // Encode the HMAC-SHA512 hash in Base64
        return base64_encode($hmacDigest);
        
    } catch (Exception $e) {

        return false;
    }
}

// Security: Validate API configuration
$apiKey = trim($gatewayParams['apiKey'] ?? '');
$apiSecret = trim($gatewayParams['apiSecret'] ?? '');

if (empty($apiKey) || empty($apiSecret)) {
    header('HTTP/1.1 503 Service Unavailable');
    die("Gateway configuration error");
}

// Security: Calculate expected signature
$expectedSignature = calculateExpectedSignature($amount, $orderId, $apiKey, $apiSecret);
if ($expectedSignature === false) {
    header('HTTP/1.1 500 Internal Server Error');
    die("Signature calculation error");
}

/**
 * Validate Callback Invoice ID.
 */
try {
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
} catch (Exception $e) {
    header('HTTP/1.1 400 Bad Request');
    die("Invalid invoice ID");
}

/**
 * Check Callback Transaction ID.
 */
try {
    checkCbTransID($transactionId);
} catch (Exception $e) {
    header('HTTP/1.1 409 Conflict');
    die("Duplicate transaction");
}

/**
 * Security: Validate signature using timing-safe comparison
 */
if (!hash_equals($expectedSignature, $secret)) {
    header('HTTP/1.1 401 Unauthorized');
    die("Signature verification failed");
}

/**
 * Process payment based on status
 */
try {
    if ($status === 'fulfilled') {
        // Send response immediately to prevent timeouts
        header('HTTP/1.1 200 OK');
        header('Content-Type: text/plain');
        echo "Payment processed successfully";
        
        // Flush output to ensure response is sent immediately
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();
        
        // Continue with payment processing in background (after response is sent)
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        // Security: Validate payment amount against invoice (in background)
        $invoiceData = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
        if ($invoiceData['result'] === 'success') {
            $invoiceTotal = (float)$invoiceData['total'];
            $tolerance = 0.01; // 1 cent tolerance
            
            if (abs($paymentAmount - $invoiceTotal) > $tolerance) {
                // Don't die here since response already sent - just exit
                exit(1);
            }
        }
        
        // Add successful payment (in background)
        $paymentResult = addInvoicePayment(
            $invoiceId,
            $transactionId,
            $paymentAmount,
            0, // No payment fee information from Kaza Wallet
            $gatewayModuleName
        );
        
    } else {
        // Payment failed, pending, or cancelled
        // Send response immediately
        header('HTTP/1.1 200 OK');
        header('Content-Type: text/plain');
        echo "Payment status: $status";
        
        // Flush output to ensure response is sent immediately
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();
        
        // Continue processing in background (after response is sent)
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain');
    echo "Payment processing error";
    
    // Flush output even for errors
    if (ob_get_level()) {
        ob_end_flush();
    }
    flush();
}

// Exit cleanly without die() to ensure proper response
exit(0);
