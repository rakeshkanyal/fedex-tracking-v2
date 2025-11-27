<?php
/**
 * Page 1: File Upload
 */

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

// --- HANDLE POST REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        $_SESSION['error'] = "No files uploaded.";
        header("Location: index.php");
        exit;
    }

    try {
        require_once 'functions.php';
        
        // Validate and get project number (REQUIRED)
        $projectNumber = trim($_POST['project_number'] ?? '');
        if (empty($projectNumber)) {
            $_SESSION['error'] = "Project number is required.";
            header("Location: index.php");
            exit;
        }
        
        // Sanitize project number (remove special characters)
        $projectNumber = preg_replace('/[^a-zA-Z0-9_-]/', '', $projectNumber);
        if (strlen($projectNumber) > 10) {
            $projectNumber = substr($projectNumber, 0, 10);
        }
        
        // Validate and get shipment date (OPTIONAL)
        $shipmentDate = null;
        if (!empty($_POST['shipment_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $_POST['shipment_date']);
            if ($date) {
                $shipmentDate = $date->format('Y-m-d');
            }
        }
        
        // Upload and combine files
        $combinedFilePath = handleFileUploads($_FILES['files']);
        
        // Store in session for processing page
        $_SESSION['uploaded_file'] = $combinedFilePath;
        $_SESSION['shipment_date'] = $shipmentDate;
        $_SESSION['project_number'] = $projectNumber;
        
        // Redirect to processing page
        header("Location: process.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: index.php");
        exit;
    }
}

// Get error message if any
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FedEx Tracking & POD Downloader</title>
    <link rel="stylesheet" href="css/base.css">
</head>
<body>
    <div class="container">
        <h1>🚚 FedEx Tracking & POD Downloader</h1>
        <p class="subtitle">Upload Excel files with tracking numbers to check status and download PODs</p>
        
        <!-- Navigation to Address Validation -->
        <div style="margin-bottom: 20px; text-align: right;">
            <a href="address-validation.php" style="display: inline-block; padding: 10px 20px; background: #4caf50; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">
                📍 Address Validation
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="upload-section">
            <form action="" method="POST" enctype="multipart/form-data" id="uploadForm">
                <div style="margin-bottom: 20px;">
                    <label for="projectNumber" style="display: block; margin-bottom: 8px; font-weight: bold; color: #333;">
                        Project Number: <span style="color: red;">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="project_number" 
                        id="projectNumber"
                        maxlength="10"
                        required
                        style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 4px; font-size: 16px;"
                        placeholder="Enter project number (max 10 characters)"
                    >
                    <small style="display: block; margin-top: 5px; color: #666;">
                        Used for naming output files
                    </small>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label for="shipmentDate" style="display: block; margin-bottom: 8px; font-weight: bold; color: #333;">
                        Shipment Date (Optional):
                    </label>
                    <input 
                        type="date" 
                        name="shipment_date" 
                        id="shipmentDate"
                        style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 4px; font-size: 16px;"
                    >
                    <small style="display: block; margin-top: 5px; color: #666;">
                        Leave blank to search without date filter
                    </small>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label for="files" style="display: block; margin-bottom: 8px; font-weight: bold; color: #333;">
                        Excel Files: <span style="color: red;">*</span>
                    </label>
                    <input 
                        type="file" 
                        name="files[]" 
                        id="files"
                        multiple 
                        required 
                        accept=".xlsx,.xls"
                        style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 4px;"
                    >
                </div>
                
                <button type="submit" style="width: 100%;">📤 Upload & Process</button>
            </form>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 8px;">
            <h3 style="margin-top: 0;">Instructions:</h3>
            <ul style="line-height: 1.8;">
                <li><strong>Project Number:</strong> Enter a unique identifier for this batch (max 10 characters) - <span style="color: red;">Required</span></li>
                <li><strong>Shipment Date:</strong> Optional - Filter PODs by specific shipment date</li>
                <li><strong>Excel Files:</strong> Upload one or more Excel files (.xlsx or .xls)</li>
                <li>Each file must contain a column named "Tracking Number" or "Tracking"</li>
                <li>Files will be combined and processed together</li>
                <li>Output files will be named: <code>[ProjectNumber]-Tracking.xlsx</code> and <code>[ProjectNumber]-POD.pdf</code></li>
            </ul>
        </div>
    </div>
</body>
</html>