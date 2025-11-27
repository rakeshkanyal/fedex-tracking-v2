<?php
/**
 * Download All Validation Results as ZIP
 */

// Load config BEFORE starting session
require_once 'config.php';

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

// Check if we have results
if (!isset($_SESSION['address_results'])) {
    http_response_code(404);
    die('No results available');
}

$results = $_SESSION['address_results'];
$projectNumber = $results['projectNumber'] ?? 'Unknown';

// Collect all available files
$filesToZip = [];

if (!empty($results['fedexFile']) && file_exists($results['fedexFile'])) {
    $filesToZip[] = $results['fedexFile'];
}

if (!empty($results['uspsFile']) && file_exists($results['uspsFile'])) {
    $filesToZip[] = $results['uspsFile'];
}

if (!empty($results['breakResultFile']) && file_exists($results['breakResultFile'])) {
    $filesToZip[] = $results['breakResultFile'];
}

// Check if we have any files to zip
if (empty($filesToZip)) {
    http_response_code(404);
    die('No files available to download');
}

// Create ZIP file
$zipFileName = "{$projectNumber}-ValidationResults.zip";
$zipFilePath = RESULTS_FOLDER . '/' . $zipFileName;

// Remove old zip if exists
if (file_exists($zipFilePath)) {
    @unlink($zipFilePath);
}

// Create new ZIP archive
$zip = new ZipArchive();

if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    http_response_code(500);
    die('Failed to create ZIP file');
}

// Add files to ZIP
foreach ($filesToZip as $file) {
    $fileName = basename($file);
    $zip->addFile($file, $fileName);
}

$zip->close();

// Check if ZIP was created successfully
if (!file_exists($zipFilePath) || filesize($zipFilePath) === 0) {
    http_response_code(500);
    die('Failed to create ZIP file');
}

// Send ZIP file to browser
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
header('Content-Length: ' . filesize($zipFilePath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Output file
readfile($zipFilePath);

// Clean up - delete the zip file after download
@unlink($zipFilePath);

exit;