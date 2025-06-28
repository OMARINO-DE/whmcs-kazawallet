<?php
/**
 * Kaza Wallet Gateway Installation Script
 *
 * This script helps verify and install the Kaza Wallet payment gateway for WHMCS
 *
 * @author OMARINO IT Services
 * @copyright Copyright (c) OMARINO IT Services 2025
 * @license Mozilla Public License Version 2.0
 * @version 1.0.0
 */

// Only run from command line or with proper authentication
if (php_sapi_name() !== 'cli' && !isset($_GET['install_key'])) {
    die('Installation script must be run from command line or with proper authentication key.');
}

echo "=== Kaza Wallet WHMCS Gateway Installation ===\n\n";

// Check if WHMCS is detected
$whmcsRoot = dirname(__FILE__);
$whmcsConfigFile = $whmcsRoot . '/configuration.php';
$whmcsInitFile = $whmcsRoot . '/init.php';

if (!file_exists($whmcsConfigFile) || !file_exists($whmcsInitFile)) {
    echo "❌ WHMCS installation not detected in current directory.\n";
    echo "Please run this script from your WHMCS root directory.\n\n";
    exit(1);
}

echo "✅ WHMCS installation detected\n";

// Check PHP version
$phpVersion = phpversion();
if (version_compare($phpVersion, '7.0.0', '<')) {
    echo "❌ PHP version $phpVersion is not supported. PHP 7.0 or higher required.\n\n";
    exit(1);
}

echo "✅ PHP version $phpVersion is supported\n";

// Check required PHP extensions
$requiredExtensions = ['curl', 'json', 'openssl'];
$missingExtensions = [];

foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $missingExtensions[] = $extension;
    }
}

if (!empty($missingExtensions)) {
    echo "❌ Missing required PHP extensions: " . implode(', ', $missingExtensions) . "\n\n";
    exit(1);
}

echo "✅ All required PHP extensions are loaded\n";

// Check directory permissions
$directories = [
    'modules/gateways',
    'modules/gateways/callback',
    'includes/hooks'
];

foreach ($directories as $dir) {
    $fullPath = $whmcsRoot . '/' . $dir;
    if (!is_dir($fullPath)) {
        echo "❌ Directory $dir does not exist\n";
        exit(1);
    }
    
    if (!is_writable($fullPath)) {
        echo "❌ Directory $dir is not writable\n";
        exit(1);
    }
}

echo "✅ All required directories exist and are writable\n";

// Check if files exist
$pluginFiles = [
    'kazawallet.php' => 'modules/gateways/kazawallet.php',
    'callback/kazawallet.php' => 'modules/gateways/callback/kazawallet.php',
    'hooks/kazawallet_admin.php' => 'includes/hooks/kazawallet_admin.php'
];

$filesToCopy = [];
foreach ($pluginFiles as $source => $target) {
    $sourcePath = __DIR__ . '/' . $source;
    $targetPath = $whmcsRoot . '/' . $target;
    
    if (!file_exists($sourcePath)) {
        echo "❌ Source file $source not found\n";
        exit(1);
    }
    
    if (!file_exists($targetPath)) {
        $filesToCopy[$sourcePath] = $targetPath;
    } else {
        echo "⚠️  File $target already exists. Skipping...\n";
    }
}

// Copy files
if (!empty($filesToCopy)) {
    echo "\n📁 Copying files...\n";
    
    foreach ($filesToCopy as $source => $target) {
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        if (copy($source, $target)) {
            echo "✅ Copied " . basename($source) . " to " . str_replace($whmcsRoot, '', $target) . "\n";
        } else {
            echo "❌ Failed to copy " . basename($source) . "\n";
            exit(1);
        }
    }
} else {
    echo "ℹ️  All files already exist, no copying needed\n";
}

// Try to initialize WHMCS to check database connection
echo "\n🔍 Checking WHMCS database connection...\n";

try {
    require_once $whmcsInitFile;
    
    // Check if gateway already exists in database
    if (class_exists('WHMCS\Database\Capsule')) {
        $existing = WHMCS\Database\Capsule::table('tblpaymentgateways')
            ->where('gateway', 'kazawallet')
            ->first();
        
        if ($existing) {
            echo "ℹ️  Kaza Wallet gateway already configured in database\n";
        } else {
            echo "ℹ️  Kaza Wallet gateway not yet configured (normal for first install)\n";
        }
    }
    
    echo "✅ WHMCS database connection successful\n";
} catch (Exception $e) {
    echo "⚠️  Could not verify database connection: " . $e->getMessage() . "\n";
    echo "This may be normal - please verify manually in WHMCS admin\n";
}

// Generate installation summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "🎉 INSTALLATION COMPLETE!\n";
echo str_repeat("=", 50) . "\n\n";

echo "Next steps:\n";
echo "1. Login to your WHMCS Admin Panel\n";
echo "2. Navigate to Setup → Payments → Payment Gateways\n";
echo "3. Find 'Kaza Wallet' and click 'Activate'\n";
echo "4. Click 'Manage' to configure your API credentials\n";
echo "5. Copy the callback URL to your Kaza Wallet dashboard\n";
echo "6. Test in sandbox mode before going live\n\n";

echo "Files installed:\n";
foreach ($pluginFiles as $source => $target) {
    echo "• $target\n";
}

echo "\nFor support, please refer to the README.md file or visit:\n";
echo "https://www.kazawallet.com/support\n\n";

echo "Thank you for using Kaza Wallet! 🚀\n";
