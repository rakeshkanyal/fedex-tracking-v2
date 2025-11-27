<?php
/**
 * Configuration File
 * Contains all constants, credentials, and settings
 */

// Basic Authentication Credentials
define('VALID_USER', 'jow_lpd');
define('VALID_PASS', 'jow_lpd@te#@#S123bx@@');

// PHP Settings
ini_set('memory_limit', '1G');
set_time_limit(0);

// Folder Paths
define('UPLOAD_FOLDER', __DIR__ . '/uploads');
define('POD_FOLDER', __DIR__ . '/pods');
define('RESULTS_FOLDER', __DIR__ . '/results');

$sandbox = false;

if($sandbox){
    // FedEx API Credentials for address validation
    define('FEDEX_API_KEY_AV', 'l7ca41e34b950a4cabb181e86459d6b055');
    define('FEDEX_SECRET_KEY_AV', '375b644dc2fc4bb9bb4001cf10e5cc8f');
    define('FEDEX_ACCOUNT_NUMBER', '740561073');

    // FedEx API Endpoints - SANDBOX
    define('FEDEX_AUTH_URL', 'https://apis-sandbox.fedex.com/oauth/token');
    define('FEDEX_TRACKING_URL', 'https://apis-sandbox.fedex.com/track/v1/trackingnumbers');
    define('POD_DOCUMENT_URL', 'https://apis-sandbox.fedex.com/track/v1/trackingdocuments');
    define('ADDRESS_VALIDATION_URL', 'https://apis-sandbox.fedex.com/address/v1/addresses/resolve');
} else {
    // FedEx API Credentials for Tracking API 
    define('FEDEX_API_KEY', 'l7d12c6da959db4f6e87630b4d1314ce1b');
    define('FEDEX_SECRET_KEY', '8d8ee31b892f4dd9b48453e3a5931795');
    define('FEDEX_ACCOUNT_NUMBER', '301162634');

    // FedEx API Credentials for Address validation API
    define('FEDEX_API_KEY_AV', 'l78fa0db578d964a7d961e4366f26a3a8d');
    define('FEDEX_SECRET_KEY_AV', '53d539ac7af44269b8b0332b1a4983db');

    // FedEx API Endpoints
    define('FEDEX_AUTH_URL', 'https://apis.fedex.com/oauth/token');
    define('FEDEX_TRACKING_URL', 'https://apis.fedex.com/track/v1/trackingnumbers');
    define('POD_DOCUMENT_URL', 'https://apis.fedex.com/track/v1/trackingdocuments');
    define('ADDRESS_VALIDATION_URL', 'https://apis.fedex.com/address/v1/addresses/resolve');
}

// API Settings
define('MAX_RETRIES', 4);
define('RETRY_DELAY_SECONDS', 3);
define('API_SLEEP_SECONDS', .6);

ini_set('session.gc_maxlifetime', 4800); // 1 hour in seconds
session_set_cookie_params(4800); // Cookie expires in 1 hour

// Create required folders if they don't exist
foreach ([UPLOAD_FOLDER, POD_FOLDER, RESULTS_FOLDER] as $folder) {
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
    }
}