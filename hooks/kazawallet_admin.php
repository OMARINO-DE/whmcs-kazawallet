<?php
/**
 * WHMCS Kaza Wallet Admin Hooks
 *
 * @author OMARINO IT Services
 * @copyright Copyright (c) OMARINO IT Services 2025
 * @license Mozilla Public License Version 2.0
 * @version 1.0.0
 */

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Hook to add custom admin area functionality for Kaza Wallet
 */
add_hook('AdminAreaPage', 1, function($vars) {
    if ($vars['filename'] == 'configgateways' && isset($_GET['action']) && $_GET['action'] == 'manage' && isset($_GET['gateway']) && $_GET['gateway'] == 'kazawallet') {
        
        // Get system URL
        $systemUrl = rtrim(WHMCS\Config\Setting::getValue('SystemURL'), '/');
        $callbackUrl = $systemUrl . '/modules/gateways/callback/kazawallet.php';
        
        // Update the callback URL in the database
        try {
            Capsule::table('tblpaymentgateways')
                ->where('gateway', 'kazawallet')
                ->where('setting', 'callback_url')
                ->update(['value' => $callbackUrl]);
        } catch (Exception $e) {
            // Handle error silently
        }
        
        // Add JavaScript to make callback URL field copyable and display instructions
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            // Find the callback URL field
            var callbackField = document.querySelector("input[name*=\'callback_url\']");
            if (callbackField) {
                callbackField.value = "' . $callbackUrl . '";
                callbackField.readOnly = true;
                callbackField.style.backgroundColor = "#f8f9fa";
                
                // Add copy button
                var copyBtn = document.createElement("button");
                copyBtn.type = "button";
                copyBtn.className = "btn btn-sm btn-info";
                copyBtn.innerHTML = "Copy";
                copyBtn.style.marginLeft = "10px";
                
                copyBtn.onclick = function() {
                    callbackField.select();
                    document.execCommand("copy");
                    copyBtn.innerHTML = "Copied!";
                    copyBtn.className = "btn btn-sm btn-success";
                    setTimeout(function() {
                        copyBtn.innerHTML = "Copy";
                        copyBtn.className = "btn btn-sm btn-info";
                    }, 2000);
                };
                
                // Insert copy button after the field
                callbackField.parentNode.insertBefore(copyBtn, callbackField.nextSibling);
                
                // Add help text
                var helpText = document.createElement("div");
                helpText.className = "help-block";
                helpText.innerHTML = "<strong>Important:</strong> Copy this callback URL and add it to your Kaza Wallet dashboard webhook settings.";
                helpText.style.marginTop = "10px";
                helpText.style.color = "#31708f";
                helpText.style.backgroundColor = "#d9edf7";
                helpText.style.border = "1px solid #bce8f1";
                helpText.style.padding = "10px";
                helpText.style.borderRadius = "4px";
                
                callbackField.parentNode.appendChild(helpText);
            }
        });
        </script>';
    }
});

/**
 * Hook to add Kaza Wallet configuration page
 */
add_hook('AdminAreaHeadOutput', 1, function($vars) {
    if ($vars['filename'] == 'configgateways') {
        return '<style>
        .kazawallet-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .kazawallet-info h4 {
            color: #495057;
            margin-top: 0;
        }
        .kazawallet-status-badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        .kazawallet-status-active {
            background-color: #28a745;
        }
        .kazawallet-status-inactive {
            background-color: #dc3545;
        }
        </style>';
    }
});

/**
 * Add menu item for Kaza Wallet in admin sidebar
 */
add_hook('AdminAreaClientSummaryActionLinks', 1, function($vars) {
    return array();
});

/**
 * Hook to validate Kaza Wallet settings
 */
add_hook('AdminAreaPageOutput', 1, function($vars) {
    if ($vars['filename'] == 'configgateways' && isset($_GET['gateway']) && $_GET['gateway'] == 'kazawallet') {
        
        // Get gateway settings
        $settings = Capsule::table('tblpaymentgateways')
            ->where('gateway', 'kazawallet')
            ->pluck('value', 'setting');
        
        $apiKey = $settings['api_key'] ?? '';
        $apiSecret = $settings['api_secret'] ?? '';
        $testMode = $settings['test_mode'] ?? '';
        
        // Check if settings are configured
        $isConfigured = !empty($apiKey) && !empty($apiSecret);
        
        $statusBadge = $isConfigured 
            ? '<span class="kazawallet-status-badge kazawallet-status-active">Configured</span>'
            : '<span class="kazawallet-status-badge kazawallet-status-inactive">Not Configured</span>';
        
        $modeText = $testMode ? 'Test Mode' : 'Live Mode';
        
        $infoBox = '
        <div class="kazawallet-info">
            <h4>Kaza Wallet Gateway Status</h4>
            <p><strong>Status:</strong> ' . $statusBadge . '</p>
            <p><strong>Mode:</strong> ' . $modeText . '</p>
            <p><strong>API Key:</strong> ' . (!empty($apiKey) ? substr($apiKey, 0, 8) . '...' : 'Not Set') . '</p>
            <p><strong>API Secret:</strong> ' . (!empty($apiSecret) ? 'Set' : 'Not Set') . '</p>
            <hr>
            <h5>Setup Instructions:</h5>
            <ol>
                <li>Get your API credentials from your Kaza Wallet merchant dashboard</li>
                <li>Enter your API Key and API Secret above</li>
                <li>Copy the Callback URL and add it to your Kaza Wallet webhook settings</li>
                <li>Test the integration in test mode first</li>
                <li>Switch to live mode when ready for production</li>
            </ol>
            <p><strong>API Endpoint:</strong> https://outdoor.kasroad.com</p>
            <p><strong>Support:</strong> Visit <a href="https://www.kazawallet.com/support" target="_blank">Kaza Wallet Support</a> for help</p>
        </div>';
        
        // Inject the info box into the page
        return str_replace('</form>', '</form>' . $infoBox, $vars['content']);
    }
    
    return $vars;
});
