<?php
/**
 * Page 2: Processing with Real-time Status
 */

require_once 'config.php';
require_once 'functions.php';

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
if (!isset($_SESSION['uploaded_file'])) {
    $_SESSION['error'] = "No file to process. Please upload files first.";
    header("Location: index.php");
    exit;
}

// Check if this is an SSE request
if (isset($_GET['stream']) && $_GET['stream'] === '1') {
    // Set headers for Server-Sent Events
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    
    try {
        $filePath = $_SESSION['uploaded_file'];
        $projectNumber = $_SESSION['project_number'];
        $shipmentDate = $_SESSION['shipment_date'] ?? null;
        
        logStatus('Starting FedEx tracking process...', 'info', 0);
        
        // Authenticate
        logStatus('Authenticating with FedEx API...', 'info', 5);
        $accessToken = getFedexAccessToken();
        logStatus('✓ Authentication successful', 'success', 10);
        
        // Process tracking file
        $results = processTrackingFile($filePath, $accessToken, $projectNumber, $shipmentDate);
        
        // Store results in session
        $_SESSION['results'] = $results;
        
        // Clear uploaded file from session
        unset($_SESSION['uploaded_file']);
        unset($_SESSION['shipment_date']);
        unset($_SESSION['project_number']);
        
        logStatus('✓ Processing complete!', 'success', 100);
        
        // Send completion event
        sendSSE('complete', [
            'success' => true,
            'hasExcel' => !empty($results['excel']),
            'hasMergedPdf' => !empty($results['mergedPdf']),
            'pdfCount' => count($results['individualPdfs'])
        ]);
        
    } catch (Exception $e) {
        logStatus('✗ Error: ' . $e->getMessage(), 'error');
        sendSSE('complete', ['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}

// Regular page view - show processing UI
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing - FedEx Tracker</title>
    <link rel="stylesheet" href="css/base.css">
</head>
<body>
    <div class="container">
        <h1>🚚 FedEx Tracking & POD Downloader</h1>
        <p class="subtitle">Processing your tracking numbers...</p>

        <div class="progress-section" id="progressSection">
            <div class="progress-header">
                <div class="spinner"></div>
                <h2 style="border: none; margin: 0; padding: 0;">Processing...</h2>
            </div>
            
            <div class="progress-bar-container">
                <div class="progress-bar" id="progressBar">0%</div>
            </div>
            
            <div class="status-log" id="statusLog">
                <div class="status-item info">
                    <span class="time">--:--:--</span>
                    <span>Initializing...</span>
                </div>
            </div>
        </div>

        <script>
            // Server-Sent Events for real-time status updates
            const eventSource = new EventSource('process.php?stream=1');
            const statusLog = document.getElementById('statusLog');
            const progressBar = document.getElementById('progressBar');
            const progressSection = document.getElementById('progressSection');
            
            let hasCompleted = false;
            
            // Clear initial placeholder
            statusLog.innerHTML = '';
            
            eventSource.addEventListener('status', function(e) {
                const data = JSON.parse(e.data);
                
                // Add status message to log
                const statusItem = document.createElement('div');
                statusItem.className = `status-item ${data.type}`;
                statusItem.innerHTML = `
                    <span class="time">${data.timestamp}</span>
                    <span>${data.message}</span>
                `;
                statusLog.appendChild(statusItem);
                statusLog.scrollTop = statusLog.scrollHeight;
                
                // Update progress bar
                if (data.progress !== null && data.progress !== undefined) {
                    progressBar.style.width = data.progress + '%';
                    progressBar.textContent = data.progress + '%';
                }
            });
            
            eventSource.addEventListener('complete', function(e) {
                if (hasCompleted) return;
                hasCompleted = true;
                
                const data = JSON.parse(e.data);
                eventSource.close();
                
                if (data.success) {
                    const successItem = document.createElement('div');
                    successItem.className = 'status-item success';
                    successItem.innerHTML = `
                        <span class="time">${new Date().toLocaleTimeString('en-US', {hour12: false})}</span>
                        <span>🎉 All done! Redirecting to results...</span>
                    `;
                    statusLog.appendChild(successItem);
                    statusLog.scrollTop = statusLog.scrollHeight;
                    
                    setTimeout(() => {
                        window.location.href = 'results.php';
                    }, 2000);
                } else {
                    const errorItem = document.createElement('div');
                    errorItem.className = 'status-item error';
                    errorItem.innerHTML = `
                        <span class="time">${new Date().toLocaleTimeString('en-US', {hour12: false})}</span>
                        <span>✗ Processing failed: ${data.error || 'Unknown error'}</span>
                    `;
                    statusLog.appendChild(errorItem);
                    statusLog.scrollTop = statusLog.scrollHeight;
                    
                    const spinner = progressSection.querySelector('.spinner');
                    if (spinner) spinner.style.display = 'none';
                    
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 3000);
                }
            });
            
            eventSource.onerror = function(e) {
                if (hasCompleted) return;
                
                console.error('EventSource error:', e);
                eventSource.close();
                
                const errorItem = document.createElement('div');
                errorItem.className = 'status-item error';
                errorItem.innerHTML = `
                    <span class="time">${new Date().toLocaleTimeString('en-US', {hour12: false})}</span>
                    <span>✗ Connection error. Redirecting...</span>
                `;
                statusLog.appendChild(errorItem);
                statusLog.scrollTop = statusLog.scrollHeight;
                
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 3000);
            };
        </script>
    </div>
</body>
</html>