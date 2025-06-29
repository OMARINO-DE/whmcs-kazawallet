<?php
/**
 * WHMCS Kaza Wallet Payment Callback File
 *
 * This file handles payment notifications (webhooks) from Kaza Wallet
 * and processes them within WHMCS. Developed by OMARINO IT Services.
 *
 * @author OMARINO IT Services
 * @copyright Copyright (c) OMARINO IT Services 2025
 * @license MIT License
 * @version 2.1.0
 * @website https://www.omarino.de
 * @support info@omarino.de
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Get the raw POST data
$rawData = file_get_contents('php://input');
$webhookData = json_decode($rawData, true);

// If JSON parsing failed, try to get POST data directly
if (!$webhookData) {
    $webhookData = $_POST;
}

// Retrieve data returned in Kaza Wallet webhook
// According to the API documentation, webhook sends:
// order_id, secret, amount, ref, status, currency
$orderId = isset($webhookData['order_id']) ? $webhookData['order_id'] : '';
$secret = isset($webhookData['secret']) ? $webhookData['secret'] : '';
$amount = isset($webhookData['amount']) ? $webhookData['amount'] : '';
$ref = isset($webhookData['ref']) ? $webhookData['ref'] : ''; // This should be our invoice ID
$status = isset($webhookData['status']) ? $webhookData['status'] : '';
$currency = isset($webhookData['currency']) ? $webhookData['currency'] : '';

// Use ref as invoice ID (as per our implementation)
$invoiceId = $ref;
$transactionId = $orderId;
$paymentAmount = $amount;

// Determine transaction status
$transactionStatus = ($status === 'fulfilled') ? 'Success' : 'Failure';

/**
 * Validate callback authenticity.
 *
 * Kaza Wallet provides a secret for verification. We need to calculate
 * the expected signature and compare it with the received secret.
 */

$apiKey = $gatewayParams['apiKey'];
$apiSecret = $gatewayParams['apiSecret'];

// Calculate expected signature using the same method as in the main module
function calculateExpectedSignature($amount, $orderId, $apiKey, $apiSecret)
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

$expectedSignature = calculateExpectedSignature($amount, $orderId, $apiKey, $apiSecret);

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 */

$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 */

checkCbTransID($transactionId);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 */

logTransaction($gatewayParams['name'], $webhookData, $transactionStatus);

/**
 * Validate signature
 */
if (hash_equals($expectedSignature, $secret)) {
    /**
     * Add Invoice Payment.
     *
     * Applies a payment transaction entry to the given invoice ID.
     *
     * @param int $invoiceId         Invoice ID
     * @param string $transactionId  Transaction ID
     * @param float $paymentAmount   Amount paid (defaults to full balance)
     * @param float $paymentFee      Payment fee (optional)
     * @param string $gatewayModule  Gateway module name
     */
    
    if ($status === 'fulfilled') {
        addInvoicePayment(
            $invoiceId,
            $transactionId,
            $paymentAmount,
            0, // No payment fee information from Kaza Wallet
            $gatewayModuleName
        );
        
        echo "Payment processed successfully";
    } else {
        // Payment failed or timed out
        echo "Payment failed or timed out";
    }
} else {
    // Signature verification failed
    logTransaction($gatewayParams['name'], array(
        'error' => 'Signature verification failed',
        'expected' => $expectedSignature,
        'received' => $secret,
        'webhook_data' => $webhookData
    ), 'Signature Verification Failed');
    
    die("Signature verification failed");
}
?>
