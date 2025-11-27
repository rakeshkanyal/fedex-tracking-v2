<?php
/**
 * Page: Address Validation Results
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
if (!isset($_SESSION['address_results'])) {
    $_SESSION['error'] = "No results available. Please upload and process files first.";
    header("Location: address-validation.php");
    exit;
}

$results = $_SESSION['address_results'];
$fedexFile = $results['fedexFile'] ?? null;
$uspsFile = $results['uspsFile'] ?? null;
$fedexCount = $results['fedexCount'] ?? 0;
$uspsCount = $results['uspsCount'] ?? 0;
$totalCount = $results['totalCount'] ?? 0;
$projectNumber = $results['projectNumber'] ?? 'Unknown';

// Get file names
$fedexFileName = $fedexFile ? basename($fedexFile) : null;
$uspsFileName = $uspsFile ? basename($uspsFile) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - Address Validation</title>
    <link rel="stylesheet" href="css/base.css">
</head>
<body>
    <div class="container">
        <h1>🚚 FedEx Address Validation</h1>
        <p class="subtitle">Validation Complete - Download Your Results</p>

        <div class="message success" style="margin-bottom: 30px;">
            ✓ Address validation completed for Project: <strong><?= htmlspecialchars($projectNumber) ?></strong>
        </div>

        <!-- Summary Section -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">
            <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #2196F3;">
                <div style="font-size: 32px; font-weight: bold; color: #2196F3;"><?= $totalCount ?></div>
                <div style="color: #666; margin-top: 8px;">Total Addresses</div>
            </div>
            <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #4caf50;">
                <div style="font-size: 32px; font-weight: bold; color: #4caf50;"><?= $fedexCount ?></div>
                <div style="color: #666; margin-top: 8px;">📦 FedEx Delivery</div>
            </div>
            <div style="background: #fff3e0; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #ff9800;">
                <div style="font-size: 32px; font-weight: bold; color: #ff9800;"><?= $uspsCount ?></div>
                <div style="color: #666; margin-top: 8px;">📬 USPS Delivery</div>
            </div>
        </div>

        <div class="results-section">
            <h2>📊 Download Files</h2>
            
            <!-- ZIP Download Button -->
            <?php if ($fedexFileName || $uspsFileName): ?>
                <div style="margin-bottom: 20px; text-align: center; padding: 20px; background: #f0f8ff; border-radius: 8px; border: 2px solid #2196F3;">
                    <a href="download-zip.php" class="download-link" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 18px; padding: 15px 40px;">
                        📦 Download Validation Results (ZIP)
                    </a>
                    <p style="margin-top: 15px; color: #666; font-size: 14px;">
                        <strong>Download all files in a single ZIP archive</strong><br>
                        <?php 
                        $fileCount = 0;
                        if ($fedexFileName) $fileCount++;
                        if ($uspsFileName) $fileCount++;
                        if (!empty($results['breakResultFile'])) $fileCount++;
                        ?>
                        Contains <?= $fileCount ?> file(s): 
                        <?php
                        $files = [];
                        if ($fedexFileName) $files[] = "FedEx List (" . $fedexCount . " addresses)";
                        if ($uspsFileName) $files[] = "USPS List (" . $uspsCount . " addresses)";
                        if (!empty($results['breakResultFile'])) $files[] = "Break Result (Groups of 100)";
                        echo implode(", ", $files);
                        ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="message error">
                    No files were generated. This might happen if:
                    <ul style="margin-top: 10px;">
                        <li>The CSV file was empty or invalid</li>
                        <li>No valid addresses were found in the file</li>
                        <li>There was an error processing the addresses</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <!-- Success Rate -->
        <?php if ($totalCount > 0): ?>
        <div style="margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 8px;">
            <h3 style="margin-top: 0;">Validation Statistics</h3>
            <div style="margin-top: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>FedEx Deliverable Rate:</span>
                    <strong style="color: #4caf50;"><?php 
                        if ($totalCount > 0) {
                            $percentage = floor(($fedexCount / $totalCount) * 1000) / 10;
                            echo number_format($percentage, 1);
                        } else {
                            echo '0.0';
                        }
                    ?>%</strong>
                </div>
                <div style="width: 100%; height: 24px; background: #e0e0e0; border-radius: 12px; overflow: hidden;">
                    <div style="height: 100%; background: linear-gradient(90deg, #4caf50 0%, #81c784 100%); width: <?php 
                        if ($totalCount > 0) {
                            $percentage = floor(($fedexCount / $totalCount) * 1000) / 10;
                            echo number_format($percentage, 1);
                        } else {
                            echo '0';
                        }
                    ?>%; transition: width 0.3s ease;"></div>
                </div>
            </div>
            <div style="margin-top: 20px; font-size: 14px; color: #666;">
                <strong><?= $fedexCount ?></strong> addresses can be shipped via FedEx<br>
                <strong><?= $uspsCount ?></strong> addresses should be shipped via USPS
            </div>
        </div>
        <?php endif; ?>

        <div style="margin-top: 30px; text-align: center;">
            <a href="address-validation.php" style="display: inline-block; padding: 12px 30px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">
                ← Validate More Addresses
            </a>
            <a href="index.php" style="display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; margin-left: 10px;">
                Go to Tracking
            </a>
            <a href="clear.php" style="display: inline-block; padding: 12px 30px; background: #f44336; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; margin-left: 10px;">
                🗑️ Clear All Files
            </a>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 8px;">
            <h3 style="margin-top: 0;">What's Next?</h3>
            <ul style="line-height: 1.8;">
                <li><strong>📦 FedEx List:</strong> Use this file for FedEx shipping - addresses are validated and guaranteed deliverable</li>
                <li><strong>📊 FedEx Break Result:</strong> Shows address ranges in groups of 100. The "Helper" column shows position within each group (0-99), making it easy to process in batches</li>
                <li><strong>📬 USPS List:</strong> Ship these addresses via USPS instead - FedEx cannot validate/deliver to these locations</li>
            </ul>
        </div>
    </div>
</body>
</html>