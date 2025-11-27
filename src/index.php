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

// Set page title
$pageTitle = 'Tracking & POD - FedEx Tracker';
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="content-container">
            <div class="page-header">
                <h2 class="page-title">
                    <span>📦</span>
                    <span>Tracking & POD Downloader</span>
                </h2>
                <p class="page-subtitle">Upload Excel files with tracking numbers to check status and download PODs</p>
            </div>
            
            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="form-section">
                <form action="" method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="form-group">
                        <label for="projectNumber" class="form-label">
                            Project Number: <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="project_number" 
                            id="projectNumber"
                            maxlength="10"
                            required
                            class="form-input"
                            placeholder="Enter project number (max 10 characters)"
                        >
                        <small class="form-help">
                            Used for naming output files
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="shipmentDate" class="form-label">
                            Shipment Date (Optional):
                        </label>
                        <input 
                            type="date" 
                            name="shipment_date" 
                            id="shipmentDate"
                            class="form-input"
                        >
                        <small class="form-help">
                            Leave blank to search without date filter
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="files" class="form-label">
                            Excel Files: <span class="required">*</span>
                        </label>
                        <input 
                            type="file" 
                            name="files[]" 
                            id="files"
                            multiple 
                            required 
                            accept=".xlsx,.xls"
                            class="form-input"
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">📤 Upload & Process</button>
                </form>
            </div>

            <div class="info-box">
                <h3>Instructions:</h3>
                <ul>
                    <li><strong>Project Number:</strong> Enter a unique identifier for this batch (max 10 characters) - <span style="color: red;">Required</span></li>
                    <li><strong>Shipment Date:</strong> Optional - Filter PODs by specific shipment date</li>
                    <li><strong>Excel Files:</strong> Upload one or more Excel files (.xlsx or .xls)</li>
                    <li>Each file must contain a column named "Tracking Number" or "Tracking"</li>
                    <li>Files will be combined and processed together</li>
                    <li>Output files will be named: <code>[ProjectNumber]-Tracking.xlsx</code> and <code>[ProjectNumber]-POD.pdf</code></li>
                </ul>
            </div>
            
            <?php include 'includes/footer.php'; ?>
