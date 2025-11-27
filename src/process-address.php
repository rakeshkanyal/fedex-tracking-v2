<?php
/**
 * Page: Address Validation Processing - SIMPLIFIED (NO SSE)
 * Processes immediately without progress display
 */

require_once 'config.php';
require_once 'address-functions.php'; // Uses custom logic, no API calls

session_start();

// --- BASIC AUTHENTICATION ---
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])
    || $_SERVER['PHP_AUTH_USER'] !== VALID_USER
    || $_SERVER['PHP_AUTH_PW'] !== VALID_PASS
) {
    header('WWW-Authenticate: Basic realm="FedEx POD Tracker"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Unauthorized access';
    exit;
}

// Check if we have a file to process
if (!isset($_SESSION['uploaded_file']) || $_SESSION['process_type'] !== 'address_validation') {
    $_SESSION['error'] = "No file to process. Please upload files first.";
    header("Location: address-validation.php");
    exit;
}

try {
    $filePath = $_SESSION['uploaded_file'];
    $projectNumber = $_SESSION['project_number'];
    
    // Verify file exists
    if (!file_exists($filePath)) {
        throw new Exception("Uploaded file not found");
    }
    
    // Process addresses using custom logic (no API calls, no SSE)
    $results = processAddressValidation($filePath, null, $projectNumber);
    
    // Store results in session
    $_SESSION['address_results'] = $results;
    
    // Clear uploaded file from session
    unset($_SESSION['uploaded_file']);
    unset($_SESSION['project_number']);
    unset($_SESSION['process_type']);
    
    // Redirect to results
    header("Location: results-address.php");
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    error_log("Address validation error: " . $e->getMessage());
    header("Location: address-validation.php");
    exit;
}