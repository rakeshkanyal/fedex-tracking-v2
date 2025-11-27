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

// Set page title
$pageTitle = 'Results - FedEx Tracker';
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="content-container">
            <div class="page-header">
                <h2 class="page-title">
                    <span>📊</span>
                    <span>Tracking Results</span>
                </h2>
                <p class="page-subtitle">Processing complete - download your files</p>
            </div>

            <div class="message success" style="margin-bottom: 30px;">
                ✓ Processing completed successfully for Project: <strong><?= htmlspecialchars($projectNumber) ?></strong>
            </div>

            <div class="card">
                <h3 class="card-title">📄 Download Files</h3>
                
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
                    <h4 style="margin-top: 30px; margin-bottom: 15px;">📦 Individual POD Documents (<?= count($individualPdfs) ?> files)</h4>
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

            <div class="action-bar">
                <a href="index.php" class="btn btn-secondary">
                    ← Process New Files
                </a>
                <a href="clear.php" class="btn btn-danger" onclick="return confirm('Clear all files and reset session?');">
                    🗑️ Clear All Files
                </a>
            </div>

            <div class="info-box">
                <h3>Legend:</h3>
                <ul>
                    <li><strong style="background: #FFFF00; padding: 2px 8px;">Yellow highlight</strong> = Delivered</li>
                    <li><strong style="background: #FF0000; color: white; padding: 2px 8px;">Red highlight</strong> = Not delivered yet</li>
                    <li>Individual POD files are named with tracking numbers</li>
                </ul>
            </div>
            
            <?php include 'includes/footer.php'; ?>
