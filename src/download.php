<?php
/**
 * File Download Handler
 * Securely serves files from results and pods folders
 */

session_start();

$allowedFolders = ['results', 'pods'];
$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    die('No file specified');
}

// Prevent directory traversal attacks
$file = basename($file);

// Try to find file in allowed folders
$filePath = null;
foreach ($allowedFolders as $folder) {
    $path = __DIR__ . "/$folder/" . $file;
    
    if (file_exists($path) && is_file($path)) {
        $filePath = $path;
        break;
    }
}

if (!$filePath) {
    http_response_code(404);
    die('File not found: ' . htmlspecialchars($file));
}

// Check if file is readable
if (!is_readable($filePath)) {
    http_response_code(403);
    die('File not readable. Check permissions.');
}

// Determine content type based on extension
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$contentType = match($ext) {
    'pdf' => 'application/pdf',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    default => 'application/octet-stream'
};

// Send appropriate headers
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Output file
readfile($filePath);
exit;