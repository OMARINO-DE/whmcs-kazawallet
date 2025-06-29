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
 * @license MIT License
 * @version 3.0.0
 * @website https://www.omarino.de
 * @support info@omarino.de
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 */

// Enhanced webhook monitoring and general callback logger
function logWebhookActivity($type, $message, $data = null) {
    $logFile = __DIR__ . '/../webhook-monitor.log';
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $type,
        'message' => $message,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown'
    ];
    
    $logLine = json_encode($entry) . "\n";
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

// Detailed callback logger for every single request
function logCallbackDetail($level, $message, $data = null) {
    $logFile = __DIR__ . '/../callback-detailed.log';
    $timestamp = date('Y-m-d H:i:s T');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    
    $logEntry = "[$timestamp] [$level] [$ip] [$method $uri] $message";
    if ($data) {
        $logEntry .= " | Data: " . json_encode($data);
    }
    $logEntry .= " | UA: $userAgent\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Log ALL incoming requests immediately (before any processing)
logCallbackDetail('INFO', 'Callback request received', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? '0',
    'query_string' => $_SERVER['QUERY_STRING'] ?? '',
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
    'https' => $_SERVER['HTTPS'] ?? 'off',
    'request_time' => $_SERVER['REQUEST_TIME'] ?? time()
]);

// Log all incoming webhook requests for webhook monitor
logWebhookActivity('webhook', 'Callback request received', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
    'query_string' => $_SERVER['QUERY_STRING'] ?? '',
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
    'headers' => array_filter($_SERVER, function($key) {
        return strpos($key, 'HTTP_') === 0;
    }, ARRAY_FILTER_USE_KEY)
]);

// Security: Allow POST requests for webhooks (but verify they're legitimate)
if (!isset($_SERVER['REQUEST_METHOD'])) {
    logCallbackDetail('ERROR', 'Invalid request method - REQUEST_METHOD not set');
    logWebhookActivity('error', 'Invalid request method');
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid Request');
}

// Security: Only allow POST requests for webhooks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logCallbackDetail('ERROR', 'Method not allowed', ['method' => $_SERVER['REQUEST_METHOD']]);
    logWebhookActivity('error', 'Method not allowed: ' . $_SERVER['REQUEST_METHOD']);
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    exit('Method Not Allowed');
}

// Security: Validate Content-Type header (relaxed for debugging)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (!empty($contentType) && stripos($contentType, 'application/json') === false && stripos($contentType, 'application/x-www-form-urlencoded') === false) {
    // Log but don't block - for debugging webhook issues
    error_log('Kaza Wallet Webhook Warning: Unexpected Content-Type: ' . $contentType);
}

// Security: Check User-Agent to ensure it's from a legitimate source (relaxed for debugging)
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (empty($userAgent) || strlen($userAgent) > 255) {
    // Log but don't block - for debugging webhook issues
    error_log('Kaza Wallet Webhook Warning: Invalid or missing User-Agent: ' . $userAgent);
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

logCallbackDetail('INFO', 'Raw POST data received', [
    'data_length' => strlen($rawData),
    'data_preview' => substr($rawData, 0, 200),
    'post_data' => $_POST,
    'get_data' => $_GET
]);

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Log webhook processing for audit trail
error_log('Kaza Wallet Webhook: Processing payment notification from IP: ' . $clientIp);

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    header('HTTP/1.1 503 Service Unavailable');
    error_log('Kaza Wallet Webhook Error: Module not activated');
    die("Module Not Activated");
}
if (empty($rawData) || strlen($rawData) > 10240) { // Max 10KB
    logCallbackDetail('ERROR', 'Invalid request data size', ['data_size' => strlen($rawData), 'raw_data' => $rawData]);
    logWebhookActivity('error', 'Invalid request data size', ['size' => strlen($rawData)]);
    header('HTTP/1.1 400 Bad Request');
    die("Invalid request data");
}

logCallbackDetail('INFO', 'Data size validation passed', ['data_size' => strlen($rawData)]);

// Security: Validate JSON structure
$webhookData = json_decode($rawData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logCallbackDetail('WARNING', 'JSON decode failed, falling back to POST data', ['json_error' => json_last_error_msg(), 'raw_data' => $rawData]);
    logWebhookActivity('info', 'JSON decode failed, falling back to POST data', ['json_error' => json_last_error_msg()]);
    // Fallback to POST data with security validation
    $webhookData = array_map(function($value) {
        return is_string($value) ? filter_var($value, FILTER_SANITIZE_STRING) : $value;
    }, $_POST);
    logCallbackDetail('INFO', 'Using POST data as fallback', ['post_data' => $_POST, 'processed_data' => $webhookData]);
} else {
    logCallbackDetail('INFO', 'JSON data parsed successfully', ['webhook_data' => $webhookData]);
}

if (empty($webhookData)) {
    logCallbackDetail('ERROR', 'No webhook data available after processing', ['raw_data' => $rawData, 'post_data' => $_POST]);
    logWebhookActivity('error', 'No webhook data received');
    header('HTTP/1.1 400 Bad Request');
    die("No webhook data received");
}

logWebhookActivity('info', 'Webhook data parsed successfully', $webhookData);

// Security: Validate required webhook fields - handle both 'order_id' and 'id' fields
$requiredFields = ['secret', 'amount', 'ref', 'status', 'currency'];
foreach ($requiredFields as $field) {
    if (!isset($webhookData[$field]) || empty($webhookData[$field])) {
        logCallbackDetail('ERROR', "Missing required field: $field", $webhookData);
        header('HTTP/1.1 400 Bad Request');
        die("Missing required field: $field");
    }
}

// Handle order_id vs id field name difference
if (!isset($webhookData['order_id']) && !isset($webhookData['id'])) {
    logCallbackDetail('ERROR', 'Missing order_id/id field', $webhookData);
    header('HTTP/1.1 400 Bad Request');
    die("Missing required field: order_id or id");
}

// Use either order_id or id field
$orderId = isset($webhookData['order_id']) ? $webhookData['order_id'] : $webhookData['id'];

logCallbackDetail('INFO', 'Using order ID field', ['order_id' => $orderId, 'has_order_id' => isset($webhookData['order_id']), 'has_id' => isset($webhookData['id'])]);

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
        error_log('Kaza Wallet Callback Signature Error: ' . $e->getMessage());
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
    logWebhookActivity('info', 'Validating invoice ID', ['invoice_id' => $invoiceId]);
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
    logWebhookActivity('info', 'Invoice ID validated successfully', ['invoice_id' => $invoiceId]);
} catch (Exception $e) {
    logWebhookActivity('error', 'Invalid invoice ID', ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);
    header('HTTP/1.1 400 Bad Request');
    error_log('Kaza Wallet Callback - Invalid Invoice ID: ' . $e->getMessage());
    die("Invalid invoice ID");
}

/**
 * Check Callback Transaction ID.
 */
try {
    logWebhookActivity('info', 'Checking transaction ID for duplicates', ['transaction_id' => $transactionId]);
    checkCbTransID($transactionId);
    logWebhookActivity('info', 'Transaction ID is unique', ['transaction_id' => $transactionId]);
} catch (Exception $e) {
    logWebhookActivity('error', 'Duplicate transaction detected', ['transaction_id' => $transactionId, 'error' => $e->getMessage()]);
    header('HTTP/1.1 409 Conflict');
    error_log('Kaza Wallet Callback - Duplicate Transaction: ' . $e->getMessage());
    die("Duplicate transaction");
}

/**
 * Security: Validate signature using timing-safe comparison
 */
if (!hash_equals($expectedSignature, $secret)) {
    // Security: Log failed signature verification attempt
    logTransaction($gatewayParams['name'], array(
        'error' => 'Signature verification failed',
        'status' => 'Security Violation',
        'ip' => $clientIp,
        'timestamp' => date('Y-m-d H:i:s')
    ), 'Signature Verification Failed');
    
    header('HTTP/1.1 401 Unauthorized');
    die("Signature verification failed");
}

/**
 * Log Transaction for audit trail
 */
logTransaction($gatewayParams['name'], array(
    'order_id' => $orderId,
    'amount' => $paymentAmount,
    'currency' => $currency,
    'status' => $status,
    'invoice_id' => $invoiceId,
    'ip' => $clientIp,
    'timestamp' => date('Y-m-d H:i:s')
), $transactionStatus);

/**
 * Process payment based on status
 */
try {
    if ($status === 'fulfilled') {
        error_log('Kaza Wallet Webhook: Processing fulfilled payment for invoice ' . $invoiceId);
        
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
            
            error_log('Kaza Wallet Webhook: Invoice total: ' . $invoiceTotal . ', Payment amount: ' . $paymentAmount);
            
            if (abs($paymentAmount - $invoiceTotal) > $tolerance) {
                logTransaction($gatewayParams['name'], array(
                    'error' => 'Amount mismatch',
                    'expected' => $invoiceTotal,
                    'received' => $paymentAmount,
                    'invoice_id' => $invoiceId
                ), 'Amount Verification Failed');
                
                error_log('Kaza Wallet Webhook ERROR: Amount mismatch - Expected: ' . $invoiceTotal . ', Received: ' . $paymentAmount);
                // Don't die here since response already sent - just log the error
                error_log('=== KAZA WALLET WEBHOOK END (Amount Mismatch) ===');
                exit(1);
            }
        }
        
        // Add successful payment (in background)
        error_log('Kaza Wallet Webhook: Adding payment to invoice ' . $invoiceId . ' with transaction ID ' . $transactionId);
        logWebhookActivity('info', 'Processing payment', [
            'invoice_id' => $invoiceId,
            'transaction_id' => $transactionId,
            'amount' => $paymentAmount,
            'currency' => $currency
        ]);
        
        $paymentResult = addInvoicePayment(
            $invoiceId,
            $transactionId,
            $paymentAmount,
            0, // No payment fee information from Kaza Wallet
            $gatewayModuleName
        );
        
        error_log('Kaza Wallet Webhook: Payment addition result: ' . ($paymentResult ? 'SUCCESS' : 'FAILED'));
        logWebhookActivity($paymentResult ? 'webhook' : 'error', 
            'Payment processing ' . ($paymentResult ? 'successful' : 'failed'), 
            ['invoice_id' => $invoiceId, 'payment_result' => $paymentResult]
        );
        
        // Log successful payment processing (in background)
        logTransaction($gatewayParams['name'], array(
            'order_id' => $orderId,
            'amount' => $paymentAmount,
            'currency' => $currency,
            'status' => $status,
            'invoice_id' => $invoiceId,
            'transaction_id' => $transactionId,
            'payment_result' => $paymentResult ? 'success' : 'failed',
            'ip' => $clientIp,
            'timestamp' => date('Y-m-d H:i:s')
        ), 'Payment Processed');
        
    } else {
        // Payment failed, pending, or cancelled
        error_log('Kaza Wallet Webhook: Payment status is ' . $status . ' for invoice ' . $invoiceId);
        
        // Send response immediately
        header('HTTP/1.1 200 OK');
        header('Content-Type: text/plain');
        echo "Payment status: $status";
        
        // Flush output to ensure response is sent immediately
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();
        
        // Continue with logging in background (after response is sent)
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        logTransaction($gatewayParams['name'], array(
            'order_id' => $orderId,
            'amount' => $paymentAmount,
            'currency' => $currency,
            'status' => $status,
            'invoice_id' => $invoiceId,
            'ip' => $clientIp,
            'timestamp' => date('Y-m-d H:i:s')
        ), 'Payment Status: ' . ucfirst($status));
    }
    
} catch (Exception $e) {
    error_log('Kaza Wallet Callback Processing Error: ' . $e->getMessage());
    error_log('Kaza Wallet Callback Stack Trace: ' . $e->getTraceAsString());
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