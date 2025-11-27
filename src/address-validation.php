<?php
/**
 * Page: Address Validation
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
        header("Location: address-validation.php");
        exit;
    }

    try {
        // Validate and get project number (REQUIRED)
        $projectNumber = trim($_POST['project_number'] ?? '');
        if (empty($projectNumber)) {
            $_SESSION['error'] = "Project number is required.";
            header("Location: address-validation.php");
            exit;
        }
        
        // Sanitize project number (remove special characters)
        $projectNumber = preg_replace('/[^a-zA-Z0-9_-]/', '', $projectNumber);
        if (strlen($projectNumber) > 10) {
            $projectNumber = substr($projectNumber, 0, 10);
        }
        
        // Handle CSV file upload
        $uploadedFile = $_FILES['files'];
        $fileName = basename($uploadedFile['name'][0]);
        $targetPath = UPLOAD_FOLDER . '/' . $fileName;
        
        if (!move_uploaded_file($uploadedFile['tmp_name'][0], $targetPath)) {
            $_SESSION['error'] = "Failed to upload file.";
            header("Location: address-validation.php");
            exit;
        }
        
        // Store in session for processing page
        $_SESSION['uploaded_file'] = $targetPath;
        $_SESSION['project_number'] = $projectNumber;
        $_SESSION['process_type'] = 'address_validation';
        
        // Redirect to processing page
        header("Location: process-address.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: address-validation.php");
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
    <title>FedEx Address Validation</title>
    <link rel="stylesheet" href="css/base.css">
</head>
<body>
    <div class="container">
        <h1>🚚 FedEx Address Validation</h1>
        <p class="subtitle">Upload CSV files to validate FedEx delivery addresses</p>
        
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
                    <label for="files" style="display: block; margin-bottom: 8px; font-weight: bold; color: #333;">
                        CSV File: <span style="color: red;">*</span>
                    </label>
                    <input 
                        type="file" 
                        name="files[]" 
                        id="files"
                        required 
                        accept=".csv"
                        style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 4px;"
                    >
                </div>
                
                <button type="submit" style="width: 100%;">📤 Upload & Validate</button>
            </form>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 8px;">
            <h3 style="margin-top: 0;">Instructions:</h3>
            <ul style="line-height: 1.8;">
                <li><strong>Project Number:</strong> Enter a unique identifier for this batch (max 10 characters) - <span style="color: red;">Required</span></li>
                <li><strong>CSV File:</strong> Upload a CSV file with the following columns:</li>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>SAP_ID, ATTN, Company, Add1, Add2, Add3, City, St, Zip</li>
                </ul>
                <li>The system will validate each address with FedEx</li>
                <li>Output: Two files will be generated:</li>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li><strong>📦 FedEx-ListResult.csv</strong> - Addresses deliverable via FedEx</li>
                    <li><strong>📬 USPS-ListResult.csv</strong> - Addresses to ship via USPS instead</li>
                </ul>
            </ul>
        </div>

        <div style="margin-top: 20px; text-align: center;">
            <a href="index.php" style="display: inline-block; padding: 12px 30px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">
                ← Back to Tracking
            </a>
        </div>
    </div>
</body>
</html>