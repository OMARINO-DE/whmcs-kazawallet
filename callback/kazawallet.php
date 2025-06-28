<?php
/**
 * WHMCS Kaza Wallet Payment Gateway Callback Handler
 *
 * @author OMARINO IT Services
 * @copyright Copyright (c) OMARINO IT Services 2025
 * @license Mozilla Public License Version 2.0
 * @version 1.0.0
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

// Retrieve data returned in payment gateway callback
$orderId = $_POST['order_id'] ?? $_GET['order_id'] ?? '';
$secret = $_POST['secret'] ?? $_GET['secret'] ?? '';
$amount = $_POST['amount'] ?? $_GET['amount'] ?? '';
$ref = $_POST['ref'] ?? $_GET['ref'] ?? ''; // This is our invoice ID
$status = $_POST['status'] ?? $_GET['status'] ?? '';
$currency = $_POST['currency'] ?? $_GET['currency'] ?? '';

// Get raw input for verification
$rawInput = file_get_contents('php://input');
$webhookData = json_decode($rawInput, true);

// Log the callback for debugging
if (function_exists('logTransaction')) {
    logTransaction($gatewayParams['name'], array_merge($_POST, $_GET), "Callback Received");
}

/**
 * Validate callback authenticity using Kaza Wallet signature verification.
 */
$apiKey = $gatewayParams['api_key'];
$apiSecret = $gatewayParams['api_secret'];

// Create verification signature according to Kaza Wallet documentation
$secretString = $amount . ':::' . $orderId . ':::' . $apiKey;
$hashDigest = hash('sha256', $secretString, true);
$hmacDigest = hash_hmac('sha512', $hashDigest, $apiSecret, true);
$computedSignature = base64_encode($hmacDigest);

// Verify signature
if ($secret !== $computedSignature) {
    // Signature validation failed
    if (function_exists('logTransaction')) {
        logTransaction($gatewayParams['name'], [
            'order_id' => $orderId,
            'ref' => $ref,
            'computed_signature' => $computedSignature,
            'received_signature' => $secret,
            'error' => 'Signature validation failed'
        ], "Signature Validation Failed");
    }
    
    http_response_code(400);
    die('Signature validation failed');
}

/**
 * Validate Callback Invoice ID.
 */
$invoiceId = $ref; // The ref field contains our invoice ID
if (function_exists('checkCbInvoiceID')) {
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
}

/**
 * Check Callback Transaction ID.
 */
$transactionId = $orderId; // Use order_id as transaction ID
if (function_exists('checkCbTransID')) {
    checkCbTransID($transactionId);
}

/**
 * Log Transaction.
 */
if (function_exists('logTransaction')) {
    logTransaction($gatewayParams['name'], $_POST, "Payment Callback");
}

/**
 * Process the payment based on status
 */
if ($status === 'fulfilled') {
    /**
     * Successful Payment.
     */
    
    // Verify payment amount
    $paymentAmount = $amount;
    $invoiceAmount = function_exists('getInvoiceBalance') ? getInvoiceBalance($invoiceId) : 0;
    
    if ($paymentAmount >= $invoiceAmount || $invoiceAmount == 0) {
        // Payment amount is sufficient
        if (function_exists('addInvoicePayment')) {
            addInvoicePayment(
                $invoiceId,
                $transactionId,
                $paymentAmount,
                0, // No fee information provided
                $gatewayModuleName
            );
        }
        
        // Send payment confirmation email
        if (function_exists('sendMessage')) {
            sendMessage('Invoice Payment Confirmation', $invoiceId);
        }
        
        if (function_exists('logTransaction')) {
            logTransaction($gatewayParams['name'], [
                'invoice_id' => $invoiceId,
                'transaction_id' => $transactionId,
                'amount' => $paymentAmount,
                'currency' => $currency,
                'status' => 'success'
            ], "Payment Successful");
        }
        
    } else {
        // Payment amount is insufficient
        if (function_exists('logTransaction')) {
            logTransaction($gatewayParams['name'], [
                'invoice_id' => $invoiceId,
                'transaction_id' => $transactionId,
                'amount_paid' => $paymentAmount,
                'amount_required' => $invoiceAmount,
                'error' => 'Insufficient amount paid'
            ], "Payment Amount Insufficient");
        }
    }
    
} elseif ($status === 'timed_out') {
    /**
     * Failed/Timed Out Payment.
     */
    
    if (function_exists('logTransaction')) {
        logTransaction($gatewayParams['name'], [
            'invoice_id' => $invoiceId,
            'transaction_id' => $transactionId,
            'status' => $status,
            'error' => 'Payment timed out'
        ], "Payment Timed Out");
    }
    
} else {
    /**
     * Unknown Status Payment.
     */
    
    if (function_exists('logTransaction')) {
        logTransaction($gatewayParams['name'], [
            'invoice_id' => $invoiceId,
            'transaction_id' => $transactionId,
            'status' => $status,
            'note' => 'Payment status is unknown'
        ], "Payment Status Unknown");
    }
}

// Respond to callback
http_response_code(200);
echo "OK";
