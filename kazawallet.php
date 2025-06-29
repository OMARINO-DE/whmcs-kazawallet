<?php
/**
 * WHMCS Kaza Wallet Payment Gateway Module
 *
 * Payment Gateway module for integrating Kaza Wallet payment processing
 * with the WHMCS platform. Developed by OMARINO IT Services.
 *
 * @author OMARINO IT Services
 * @copyright Copyright (c) OMARINO IT Services 2025
 * @license MIT License
 * @version 2.2.0
 * @website https://www.omarino.de
 * @support info@omarino.de
 * @see https://developers.whmcs.com/payment-gateways/
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
        'Developer' => 'OMARINO IT Services',
        'Version' => '2.2.0',
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
                    <p style="margin: 5px 0; color: #495057;"><strong>Gateway:</strong> Kaza Wallet Payment Gateway v2.2.0</p>
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
            'Description' => 'Enter your Kaza Wallet API Key (x-api-key)<br><small style="color: #6c757d;">Get this from your Kaza Wallet merchant dashboard</small>',
        ),
        'apiSecret' => array(
            'FriendlyName' => 'API Secret',
            'Type' => 'password',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your Kaza Wallet API Secret (x-api-secret)<br><small style="color: #6c757d;">Used for webhook signature verification</small>',
        ),
        'paymentEmail' => array(
            'FriendlyName' => 'Payment Email',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Email address registered with Kaza Wallet <br><small style="color: #6c757d;">This email must be registered in your Kaza Wallet account</small>',
        ),

        '_webhook_info' => array(
            'FriendlyName' => 'Webhook Configuration',
            'Type' => 'System',
            'Value' => '<div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 6px; padding: 15px; margin: 10px 0;">
                <h5 style="margin: 0 0 10px 0; color: #155724;"><i class="fas fa-info-circle"></i> Webhook Setup Required</h5>
                <p style="margin: 5px 0; color: #155724;">Configure this webhook URL in your Kaza Wallet merchant dashboard:</p>
                <code style="background: #f8f9fa; padding: 5px 10px; border-radius: 4px; display: block; margin: 10px 0; color: #e83e8c;">
                    ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/modules/gateways/callback/kazawallet.php
                </code>
                <p style="margin: 5px 0; color: #155724; font-size: 13px;">This is required for automatic payment confirmations.</p>
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
    // Gateway Configuration Parameters
    $apiKey = $params['apiKey'];
    $apiSecret = $params['apiSecret'];
    $paymentEmail = $params['paymentEmail'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $customerEmail = $params['clientdetails']['email'];
    
    // Use payment email from config if set, otherwise fall back to customer email
    $email = !empty($paymentEmail) ? $paymentEmail : $customerEmail;

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
            'ref' => (string)$invoiceId, // Convert to string as required by API
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
            return '<div class="alert alert-danger">Payment gateway connection error. Please try again later.</div>';
        }

        $responseData = json_decode($response, true);

        if ($httpCode !== 200 || !$responseData) {
            return '<div class="alert alert-danger">Payment gateway error. Please contact support.</div>';
        }

        // Check if payment link was created successfully
        if (isset($responseData['error'])) {
            // API returned an error
            $errorMessage = isset($responseData['error']['message']) ? $responseData['error']['message'] : 'Unknown API error';
            
            if (isset($responseData['error']['details']['key']) && $responseData['error']['details']['key'] === 'USER_NOT_FOUND') {
                return '<div class="alert alert-danger">Payment error: The email address is not registered with Kaza Wallet. Please ensure you have a Kaza Wallet account or contact support.</div>';
            } else {
                return '<div class="alert alert-danger">Payment error: ' . $errorMessage . '</div>';
            }
        } else if (isset($responseData['url']) && $responseData['url']) {
            // Success case - payment URL provided
            $paymentUrl = $responseData['url'];
            
            // Redirect to payment URL
            $htmlOutput = '<script>window.location.href = "' . htmlspecialchars($paymentUrl) . '";</script>';
            $htmlOutput .= '<div class="text-center">';
            $htmlOutput .= '<p>Redirecting to Kaza Wallet payment page...</p>';
            $htmlOutput .= '<p><a href="' . htmlspecialchars($paymentUrl) . '" class="btn btn-primary">' . $langPayNow . '</a></p>';
            $htmlOutput .= '</div>';
            
            return $htmlOutput;
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
