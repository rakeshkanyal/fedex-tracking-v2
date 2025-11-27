<?php
/**
 * Page 3: Results & Downloads
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

// Check if we have results
if (!isset($_SESSION['results'])) {
    $_SESSION['error'] = "No results available. Please upload and process files first.";
    header("Location: index.php");
    exit;
}

$results = $_SESSION['results'];
$excelFile = $results['excel'] ?? null;
$mergedPdf = $results['mergedPdf'] ?? null;
$individualPdfs = $results['individualPdfs'] ?? [];
$projectNumber = $results['projectNumber'] ?? 'Unknown';

// Get file names
$excelFileName = $excelFile ? basename($excelFile) : null;
$mergedPdfFileName = $mergedPdf ? basename($mergedPdf) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - FedEx Tracker</title>
    <link rel="stylesheet" href="css/base.css">
</head>
<body>
    <div class="container">
        <h1>🚚 FedEx Tracking & POD Downloader</h1>
        <p class="subtitle">Processing Complete - Download Your Files</p>

        <div class="message success" style="margin-bottom: 30px;">
            ✓ Processing completed successfully for Project: <strong><?= htmlspecialchars($projectNumber) ?></strong>
        </div>

        <div class="results-section">
            <h2>📊 Download Files</h2>
            
            <?php if ($excelFileName): ?>
                <div style="margin-bottom: 20px;">
                    <a href="download.php?file=<?= urlencode($excelFileName) ?>" class="download-link">
                        📄 Download Excel Report (<?= htmlspecialchars($excelFileName) ?>)
                    </a>
                    <p style="margin-top: 10px; color: #666; font-size: 14px;">
                        Contains tracking status for all numbers with color coding
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($mergedPdfFileName): ?>
                <div style="margin-bottom: 20px;">
                    <a href="download.php?file=<?= urlencode($mergedPdfFileName) ?>" class="download-link" style="background: #4caf50;">
                        📦 Download Merged POD PDF (<?= htmlspecialchars($mergedPdfFileName) ?>)
                    </a>
                    <p style="margin-top: 10px; color: #666; font-size: 14px;">
                        All POD documents combined into a single file
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($individualPdfs)): ?>
                <h3>📦 Individual POD Documents (<?= count($individualPdfs) ?> files)</h3>
                <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 15px; background: #fafafa;">
                    <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php foreach ($individualPdfs as $pdf): ?>
                        <li style="margin-bottom: 10px;">
                            <a href="download.php?file=<?= urlencode(basename($pdf)) ?>" style="color: #2196F3; text-decoration: none;">
                                📄 <?= htmlspecialchars(basename($pdf)) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!$excelFileName && !$mergedPdfFileName && empty($individualPdfs)): ?>
                <div class="message error">
                    No files were generated. This might happen if:
                    <ul style="margin-top: 10px;">
                        <li>No tracking numbers were found in the uploaded file</li>
                        <li>All tracking numbers were invalid</li>
                        <li>No shipments were delivered yet</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="index.php" style="display: inline-block; padding: 12px 30px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">
                ← Process New Files
            </a>
            <a href="clear.php" style="display: inline-block; padding: 12px 30px; background: #f44336; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; margin-left: 10px;">
                🗑️ Clear All Files
            </a>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 8px;">
            <h3 style="margin-top: 0;">Legend:</h3>
            <ul style="line-height: 1.8;">
                <li><strong style="background: #FFFF00; padding: 2px 8px;">Yellow highlight</strong> = Delivered</li>
                <li><strong style="background: #FF0000; color: white; padding: 2px 8px;">Red highlight</strong> = Not delivered yet</li>
                <li>Individual POD files are named with tracking numbers</li>
            </ul>
        </div>
    </div>
</body>
</html>