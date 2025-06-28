<?php
/**
 * WHMCS Kaza Wallet Payment Gateway Module
 *
 * @author OMARINO IT Services
 * @copyright Copyright (c) OMARINO IT Services 2025
 * @license Mozilla Public License Version 2.0
 * @version 1.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Include helper functions and configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/**
 * Define module related meta data.
 */
function kazawallet_MetaData()
{
    return array(
        'DisplayName' => 'Kaza Wallet',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 */
function kazawallet_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Kaza Wallet',
        ),
        'api_key' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your Kaza Wallet API Key (x-api-key)',
        ),
        'api_secret' => array(
            'FriendlyName' => 'API Secret',
            'Type' => 'password',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your Kaza Wallet API Secret (x-api-secret)',
        ),
        'test_mode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Check to enable test mode for development',
        ),
        'callback_url' => array(
            'FriendlyName' => 'Callback URL',
            'Type' => 'text',
            'Size' => '100',
            'Default' => '',
            'Description' => 'Your callback URL (automatically generated - copy this to your Kaza Wallet dashboard)',
            'readonly' => true,
        ),
        'description' => array(
            'FriendlyName' => 'Payment Description',
            'Type' => 'text',
            'Size' => '50',
            'Default' => 'Payment for Order #%invoice_id%',
            'Description' => 'Description that appears on payment page (%invoice_id% will be replaced)',
        ),
    );
}

/**
 * Payment link.
 */
function kazawallet_link($params)
{
    // Gateway Configuration Parameters
    $apiKey = $params['api_key'];
    $apiSecret = $params['api_secret'];
    $testMode = $params['test_mode'];
    $description = $params['description'];
    
    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currency = $params['currency'];
    
    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    
    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleName = $params['paymentmethod'];
    
    // Build callback URL and success redirect URL
    $callbackUrl = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php';
    $successUrl = $returnUrl . '&kazawallet=success';
    
    // Update callback URL in database for display in admin
    if (class_exists('WHMCS\Database\Capsule')) {
        try {
            WHMCS\Database\Capsule::table('tblpaymentgateways')
                ->where('gateway', $moduleName)
                ->where('setting', 'callback_url')
                ->update(['value' => $callbackUrl]);
        } catch (Exception $e) {
            // Handle database error silently
        }
    }
    
    // Prepare payment data according to Kaza Wallet API
    $paymentData = array(
        'amount' => $amount,
        'currency' => $currency,
        'email' => $email,
        'ref' => $invoiceId,
        'redirectUrl' => $successUrl,
    );
    
    // Create payment link
    $paymentLink = createKazaWalletPaymentLink($apiKey, $apiSecret, $paymentData);
    
    if ($paymentLink && isset($paymentLink['payment_url'])) {
        // Return HTML for payment button
        $htmlOutput = '<form method="get" action="' . $paymentLink['payment_url'] . '">';
        $htmlOutput .= '<input type="submit" value="' . $langPayNow . '" class="btn btn-success" />';
        $htmlOutput .= '</form>';
        
        return $htmlOutput;
    } else {
        return '<div class="alert alert-danger">Error: Unable to create payment link. Please contact support.</div>';
    }
}

/**
 * Refund transaction (using withdrawal API).
 */
function kazawallet_refund($params)
{
    // Gateway Configuration Parameters
    $apiKey = $params['api_key'];
    $apiSecret = $params['api_secret'];
    $testMode = $params['test_mode'];
    
    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currency = $params['currency'];
    
    // Get merchant email from configuration (you may need to add this to config)
    $merchantEmail = $params['email'] ?? '';
    
    // Attempt refund via withdrawal
    $refundResult = processKazaWalletWithdrawal($apiKey, $apiSecret, $merchantEmail, $currency, $refundAmount, 'Refund for transaction: ' . $transactionIdToRefund);
    
    if ($refundResult && isset($refundResult['success']) && $refundResult['success'] === true) {
        return array(
            'status' => 'success',
            'rawdata' => json_encode($refundResult),
            'transid' => $refundResult['id'] ?? $transactionIdToRefund,
        );
    } else {
        return array(
            'status' => 'declined',
            'rawdata' => json_encode($refundResult),
        );
    }
}

/**
 * Create payment link via Kaza Wallet API
 */
function createKazaWalletPaymentLink($apiKey, $apiSecret, $paymentData)
{
    $url = 'https://outdoor.kasroad.com/wallet/createPaymentLink';
    
    $headers = array(
        'x-api-key: ' . $apiKey,
        'Content-Type: application/json',
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 || $httpCode === 201) {
        return json_decode($response, true);
    }
    
    // Log error for debugging
    if (function_exists('logActivity')) {
        logActivity('Kaza Wallet Payment Link Creation Failed: HTTP ' . $httpCode . ' - ' . $response);
    }
    
    return false;
}

/**
 * Process withdrawal via Kaza Wallet API (used for refunds)
 */
function processKazaWalletWithdrawal($apiKey, $apiSecret, $email, $currency, $amount, $note)
{
    $url = 'https://outdoor.kasroad.com/wallet/createWithdrawalRequest';
    
    $withdrawalData = array(
        'email' => $email,
        'currency' => $currency,
        'amount' => $amount,
        'note' => $note,
        'paymentMethod' => '37', // Default payment method - can be made configurable
        'fields' => array(
            'field1' => 'Automated refund',
            'field2' => 'WHMCS Gateway'
        )
    );
    
    $headers = array(
        'x-api-key: ' . $apiKey,
        'x-api-secret: ' . $apiSecret,
        'Content-Type: application/json',
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($withdrawalData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 || $httpCode === 201) {
        return json_decode($response, true);
    }
    
    // Log error for debugging
    if (function_exists('logActivity')) {
        logActivity('Kaza Wallet Withdrawal Failed: HTTP ' . $httpCode . ' - ' . $response);
    }
    
    return false;
}
