<?php
/**
 * Kaza Wallet API Test Script
 *
 * Simple script to test Kaza Wallet API connectivity and credentials
 *
 * @author OMARINO IT Services
 * @copyright Copyright (c) OMARINO IT Services 2025
 * @license Mozilla Public License Version 2.0
 * @version 1.0.0
 */

// Configuration - Update these with your credentials
$API_KEY = 'your_api_key_here';
$API_SECRET = 'your_api_secret_here';

echo "=== Kaza Wallet API Test ===\n\n";

if ($API_KEY === 'your_api_key_here' || $API_SECRET === 'your_api_secret_here') {
    echo "❌ Please update the API_KEY and API_SECRET variables at the top of this file\n\n";
    exit(1);
}

// API endpoint
$apiEndpoint = 'https://outdoor.kasroad.com';

echo "🔍 Testing connection to $apiEndpoint\n";
echo "🔑 API Key: " . substr($API_KEY, 0, 8) . "...\n";
echo "🔐 API Secret: " . (strlen($API_SECRET) > 0 ? 'Set' : 'Not Set') . "\n\n";

// Test 1: API Connectivity
echo "Test 1: API Connectivity\n";
echo str_repeat("-", 25) . "\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Connection failed: $error\n\n";
    exit(1);
} else {
    echo "✅ Successfully connected to API endpoint\n";
    echo "📡 HTTP Status: $httpCode\n\n";
}

// Test 2: Create Test Payment Link
echo "Test 2: Create Test Payment Link\n";
echo str_repeat("-", 32) . "\n";

$paymentData = array(
    'amount' => '10.00',
    'currency' => 'USD',
    'email' => 'test@example.com',
    'ref' => 'TEST-' . time(),
    'redirectUrl' => 'https://your-domain.com?kazawallet=success'
);

$url = $apiEndpoint . '/wallet/createPaymentLink';
$headers = array(
    'x-api-key: ' . $API_KEY,
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
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Payment link creation failed: $error\n\n";
} elseif ($httpCode === 200 || $httpCode === 201) {
    echo "✅ Payment link created successfully\n";
    $responseData = json_decode($response, true);
    if (isset($responseData['payment_url'])) {
        echo "🔗 Payment URL: " . $responseData['payment_url'] . "\n";
    }
    echo "📋 Full Response: $response\n\n";
} else {
    echo "❌ Payment link creation failed\n";
    echo "📡 HTTP Status: $httpCode\n";
    echo "📋 Response: $response\n\n";
}

// Test 3: Test Signature Generation
echo "Test 3: Test Signature Generation\n";
echo str_repeat("-", 34) . "\n";

$testAmount = '10.00';
$testOrderId = 'TEST-ORDER-123';

// Create verification signature according to Kaza Wallet documentation
$secretString = $testAmount . ':::' . $testOrderId . ':::' . $API_KEY;
$hashDigest = hash('sha256', $secretString, true);
$hmacDigest = hash_hmac('sha512', $hashDigest, $API_SECRET, true);
$signature = base64_encode($hmacDigest);

echo "✅ Signature generation test completed\n";
echo "🔐 Secret String: $secretString\n";
echo "📝 Generated Signature: $signature\n\n";

// Summary
echo str_repeat("=", 50) . "\n";
echo "🧪 Test Summary\n";
echo str_repeat("=", 50) . "\n";

echo "API Endpoint: $apiEndpoint\n";
echo "Connectivity: " . ($error ? "❌ Failed" : "✅ Success") . "\n";

if ($httpCode === 200 || $httpCode === 201) {
    echo "Payment Link Creation: ✅ Success\n";
} else {
    echo "Payment Link Creation: ❌ Failed\n";
}

echo "\n💡 Next Steps:\n";
echo "1. If tests passed, your credentials are working correctly\n";
echo "2. Install the plugin files in your WHMCS installation\n";
echo "3. Configure the gateway in WHMCS admin panel\n";
echo "4. Copy the callback URL to your Kaza Wallet dashboard\n";
echo "5. Test with real transactions in sandbox mode\n\n";

echo "🆘 Need Help?\n";
echo "• Check your API credentials in Kaza Wallet dashboard\n";
echo "• Ensure you're using the correct mode (sandbox/live)\n";
echo "• Contact Kaza Wallet support: https://www.kazawallet.com/support\n\n";
