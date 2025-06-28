<?php
/**
 * WHMCS Kaza Wallet Payment Gateway Module
 *
 * Payment Gateway module for integrating Kaza Wallet payment processing
 * with the WHMCS platform.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 * @copyright Copyright (c) 2025
 * @license MIT License
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
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
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your Kaza Wallet API Key (x-api-key)',
        ),
        'apiSecret' => array(
            'FriendlyName' => 'API Secret',
            'Type' => 'password',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your Kaza Wallet API Secret (x-api-secret)',
        ),
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Enable test mode for development',
        ),
    );
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
    // Gateway Configuration Parameters
    $apiKey = $params['apiKey'];
    $apiSecret = $params['apiSecret'];
    $testMode = $params['testMode'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $email = $params['clientdetails']['email'];

    // System Parameters
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleName = $params['paymentmethod'];

    try {
        // Create payment link via Kaza Wallet API
        $paymentData = array(
            'amount' => $amount,
            'currency' => $currencyCode,
            'email' => $email,
            'ref' => $invoiceId, // Use invoice ID as reference
            'redirectUrl' => $returnUrl . '?kazawallet=success'
        );

        $apiUrl = 'https://outdoor.kasroad.com/wallet/createPaymentLink';
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($paymentData),
            CURLOPT_HTTPHEADER => array(
                'x-api-key: ' . $apiKey,
                'Content-Type: application/json'
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return '<div class="alert alert-danger">Payment gateway connection error. Please try again later.</div>';
        }

        $responseData = json_decode($response, true);

        if ($httpCode !== 200 || !$responseData) {
            return '<div class="alert alert-danger">Payment gateway error. Please contact support.</div>';
        }

        // Check if payment link was created successfully
        if (isset($responseData['success']) && $responseData['success']) {
            $paymentUrl = isset($responseData['payment_url']) ? $responseData['payment_url'] : null;
            
            if ($paymentUrl) {
                // Redirect to payment URL
                $htmlOutput = '<script>window.location.href = "' . htmlspecialchars($paymentUrl) . '";</script>';
                $htmlOutput .= '<div class="text-center">';
                $htmlOutput .= '<p>Redirecting to Kaza Wallet payment page...</p>';
                $htmlOutput .= '<p><a href="' . htmlspecialchars($paymentUrl) . '" class="btn btn-primary">' . $langPayNow . '</a></p>';
                $htmlOutput .= '</div>';
                
                return $htmlOutput;
            }
        }

        // If we reach here, something went wrong
        return '<div class="alert alert-danger">Unable to create payment link. Please try again or contact support.</div>';

    } catch (Exception $e) {
        return '<div class="alert alert-danger">Payment processing error. Please try again later.</div>';
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
    // Gateway Configuration Parameters
    $apiKey = $params['apiKey'];
    $apiSecret = $params['apiSecret'];
    $testMode = $params['testMode'];

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $email = $params['clientdetails']['email'];

    try {
        // Create withdrawal request via Kaza Wallet API
        $withdrawalData = array(
            'email' => $email,
            'currency' => $currencyCode,
            'amount' => $refundAmount,
            'note' => 'Refund for transaction: ' . $transactionIdToRefund,
            'paymentMethod' => '37', // Default to Haram less than 3M - you may want to make this configurable
            'fields' => array(
                'name' => $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'],
                'phone' => $params['clientdetails']['phonenumber'],
                'hawalahmauto-custom-field-withdraw-3' => $params['clientdetails']['state'] // governorate
            )
        );

        $apiUrl = 'https://outdoor.kasroad.com/wallet/createWithdrawalRequest';
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($withdrawalData),
            CURLOPT_HTTPHEADER => array(
                'x-api-key: ' . $apiKey,
                'x-api-secret: ' . $apiSecret,
                'Content-Type: application/json'
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return array(
                'status' => 'error',
                'rawdata' => 'Connection error: ' . $error,
            );
        }

        $responseData = json_decode($response, true);

        if ($httpCode === 200 && isset($responseData['success']) && $responseData['success']) {
            return array(
                'status' => 'success',
                'rawdata' => $responseData,
                'transid' => isset($responseData['withdrawal_id']) ? $responseData['withdrawal_id'] : $transactionIdToRefund,
            );
        } else {
            return array(
                'status' => 'error',
                'rawdata' => $responseData ? $responseData : 'Invalid API response',
            );
        }

    } catch (Exception $e) {
        return array(
            'status' => 'error',
            'rawdata' => 'Exception: ' . $e->getMessage(),
        );
    }
}

/**
 * Helper function to verify webhook signature
 *
 * @param string $amount
 * @param string $orderId
 * @param string $apiKey
 * @param string $apiSecret
 * @return string
 */
function kazawallet_calculateSignature($amount, $orderId, $apiKey, $apiSecret)
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
