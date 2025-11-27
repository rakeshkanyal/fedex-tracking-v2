<?php
/**
 * Debug Log Viewer - View logs from browser
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

$debugLog = __DIR__ . '/debug_address_validation.log';
$sseTestLog = __DIR__ . '/sse_test.log';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Log Viewer</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #252526;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        h1 {
            color: #4ec9b0;
            margin-bottom: 10px;
            font-size: 24px;
        }
        .info {
            background: #1e3a5f;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #4ec9b0;
        }
        .log-section {
            margin-bottom: 30px;
        }
        .log-section h2 {
            color: #569cd6;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .log-content {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 4px;
            padding: 15px;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 13px;
            line-height: 1.6;
        }
        .log-line {
            margin-bottom: 5px;
        }
        .timestamp {
            color: #858585;
        }
        .error {
            color: #f48771;
            background: rgba(244, 135, 113, 0.1);
            padding: 2px 4px;
            border-radius: 3px;
        }
        .success {
            color: #4ec9b0;
        }
        .warning {
            color: #dcdcaa;
        }
        .empty {
            color: #858585;
            font-style: italic;
            text-align: center;
            padding: 30px;
        }
        .actions {
            margin-bottom: 20px;
        }
        button {
            background: #0e639c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            font-size: 14px;
        }
        button:hover {
            background: #1177bb;
        }
        .clear-btn {
            background: #c72e0f;
        }
        .clear-btn:hover {
            background: #e03e16;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 10px;
        }
        .status.exists {
            background: #1e4620;
            color: #4ec9b0;
        }
        .status.missing {
            background: #4a1e1e;
            color: #f48771;
        }
        .file-info {
            color: #858585;
            font-size: 12px;
            margin-bottom: 10px;
        }
        a {
            color: #4ec9b0;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Log Viewer</h1>
        
        <div class="info">
            <strong>💡 How to use:</strong><br>
            1. Run the address validation process<br>
            2. Click "Refresh Logs" button to see the latest logs<br>
            3. Logs update in real-time during processing<br>
            4. Check the last line to see where the process stopped
        </div>

        <div class="actions">
            <button onclick="location.reload()">🔄 Refresh Logs</button>
            <button class="clear-btn" onclick="clearLogs()">🗑️ Clear All Logs</button>
            <a href="address-validation.php"><button>← Back to Upload</button></a>
        </div>

        <!-- Address Validation Debug Log -->
        <div class="log-section">
            <h2>
                📝 Address Validation Debug Log
                <?php if (file_exists($debugLog)): ?>
                    <span class="status exists">✓ Exists (<?= number_format(filesize($debugLog)) ?> bytes)</span>
                <?php else: ?>
                    <span class="status missing">✗ Not Created Yet</span>
                <?php endif; ?>
            </h2>
            
            <?php if (file_exists($debugLog)): ?>
                <div class="file-info">
                    Last modified: <?= date('Y-m-d H:i:s', filemtime($debugLog)) ?> | 
                    Size: <?= number_format(filesize($debugLog)) ?> bytes | 
                    Lines: <?= count(file($debugLog)) ?>
                </div>
                <div class="log-content">
                    <?php
                    $lines = file($debugLog);
                    foreach ($lines as $line) {
                        $line = htmlspecialchars($line);
                        
                        // Color code different types of messages
                        if (stripos($line, 'error') !== false || stripos($line, 'exception') !== false || stripos($line, 'failed') !== false) {
                            echo '<div class="log-line error">' . $line . '</div>';
                        } elseif (stripos($line, 'success') !== false || stripos($line, '✓') !== false) {
                            echo '<div class="log-line success">' . $line . '</div>';
                        } elseif (stripos($line, 'warning') !== false || stripos($line, '⚠') !== false) {
                            echo '<div class="log-line warning">' . $line . '</div>';
                        } else {
                            echo '<div class="log-line">' . $line . '</div>';
                        }
                    }
                    ?>
                </div>
            <?php else: ?>
                <div class="log-content">
                    <div class="empty">
                        No log file found. The log will be created when you run the address validation process.<br><br>
                        Expected location: <?= $debugLog ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- SSE Test Log -->
        <div class="log-section">
            <h2>
                🧪 SSE Test Log
                <?php if (file_exists($sseTestLog)): ?>
                    <span class="status exists">✓ Exists (<?= number_format(filesize($sseTestLog)) ?> bytes)</span>
                <?php else: ?>
                    <span class="status missing">✗ Not Created Yet</span>
                <?php endif; ?>
            </h2>
            
            <?php if (file_exists($sseTestLog)): ?>
                <div class="file-info">
                    Last modified: <?= date('Y-m-d H:i:s', filemtime($sseTestLog)) ?> | 
                    Size: <?= number_format(filesize($sseTestLog)) ?> bytes | 
                    Lines: <?= count(file($sseTestLog)) ?>
                </div>
                <div class="log-content">
                    <?php
                    $lines = file($sseTestLog);
                    foreach ($lines as $line) {
                        echo '<div class="log-line">' . htmlspecialchars($line) . '</div>';
                    }
                    ?>
                </div>
            <?php else: ?>
                <div class="log-content">
                    <div class="empty">
                        No SSE test log found. This log is created when you run test-sse.html.<br><br>
                        Expected location: <?= $sseTestLog ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- System Information -->
        <div class="log-section">
            <h2>⚙️ System Information</h2>
            <div class="log-content">
                <strong>PHP Version:</strong> <?= PHP_VERSION ?><br>
                <strong>Server Software:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?><br>
                <strong>Document Root:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown' ?><br>
                <strong>Script Path:</strong> <?= __FILE__ ?><br>
                <strong>Current User:</strong> <?= get_current_user() ?><br>
                <strong>Working Directory:</strong> <?= getcwd() ?><br>
                <strong>Upload Folder:</strong> <?= UPLOAD_FOLDER ?> (<?= is_writable(UPLOAD_FOLDER) ? '✓ Writable' : '✗ Not Writable' ?>)<br>
                <strong>Results Folder:</strong> <?= RESULTS_FOLDER ?> (<?= is_writable(RESULTS_FOLDER) ? '✓ Writable' : '✗ Not Writable' ?>)<br>
                <strong>Session ID:</strong> <?= session_id() ?><br>
                <strong>Session Status:</strong> <?= session_status() === PHP_SESSION_ACTIVE ? '✓ Active' : '✗ Inactive' ?><br>
                <strong>Memory Limit:</strong> <?= ini_get('memory_limit') ?><br>
                <strong>Max Execution Time:</strong> <?= ini_get('max_execution_time') ?><br>
                <strong>Output Buffering:</strong> <?= ini_get('output_buffering') ?: 'Off' ?><br>
                <strong>Timezone:</strong> <?= date_default_timezone_get() ?><br>
            </div>
        </div>

        <!-- Session Data -->
        <div class="log-section">
            <h2>🔐 Session Data</h2>
            <div class="log-content">
                <?php if (!empty($_SESSION)): ?>
                    <?= htmlspecialchars(print_r($_SESSION, true)) ?>
                <?php else: ?>
                    <div class="empty">No session data</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script>
        function clearLogs() {
            if (confirm('Are you sure you want to clear all debug logs?')) {
                window.location.href = 'view-logs.php?action=clear';
            }
        }

        <?php if (isset($_GET['action']) && $_GET['action'] === 'clear'): ?>
            <?php
            if (file_exists($debugLog)) unlink($debugLog);
            if (file_exists($sseTestLog)) unlink($sseTestLog);
            ?>
            alert('Logs cleared successfully!');
            window.location.href = 'view-logs.php';
        <?php endif; ?>

        // Auto-refresh every 5 seconds if logs exist
        <?php if (file_exists($debugLog) || file_exists($sseTestLog)): ?>
            // Only auto-refresh if the page has been open less than 5 minutes
            let startTime = Date.now();
            setInterval(() => {
                if ((Date.now() - startTime) < 300000) { // 5 minutes
                    location.reload();
                }
            }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>