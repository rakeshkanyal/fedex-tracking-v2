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

// Set page title
$pageTitle = 'Validation Results - FedEx Tracker';
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="content-container">
            <div class="page-header">
                <h2 class="page-title">
                    <span>✅</span>
                    <span>Address Validation Results</span>
                </h2>
                <p class="page-subtitle">Validation complete - download your results</p>
            </div>

            <div class="message success" style="margin-bottom: 30px;">
                ✓ Address validation completed for Project: <strong><?= htmlspecialchars($projectNumber) ?></strong>
            </div>

            <!-- Summary Section -->
            <div class="stats-grid">
                <div class="stat-card" style="background: #e3f2fd; border-left: 4px solid #2196F3;">
                    <div class="stat-value" style="color: #2196F3;"><?= $totalCount ?></div>
                    <div class="stat-label">Total Addresses</div>
                </div>
                <div class="stat-card" style="background: #e8f5e9; border-left: 4px solid #4caf50;">
                    <div class="stat-value" style="color: #4caf50;"><?= $fedexCount ?></div>
                    <div class="stat-label">📦 FedEx Delivery</div>
                </div>
                <div class="stat-card" style="background: #fff3e0; border-left: 4px solid #ff9800;">
                    <div class="stat-value" style="color: #ff9800;"><?= $uspsCount ?></div>
                    <div class="stat-label">📬 USPS Delivery</div>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">📊 Download Files</h3>
                
                <!-- ZIP Download Button -->
                <?php if ($fedexFileName || $uspsFileName): ?>
                    <div style="margin-bottom: 20px; text-align: center; padding: 20px; background: #f0f8ff; border-radius: 8px; border: 2px solid #2196F3;">
                        <a href="download-zip.php" class="btn btn-primary" style="font-size: 18px; padding: 15px 40px;">
                            📦 Download All Files (ZIP)
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
            <div class="card">
                <h3 class="card-title">📈 Validation Statistics</h3>
                <div style="margin-top: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>FedEx Deliverable Rate:</span>
                        <strong style="color: #4caf50;"><?php 
                            $percentage = floor(($fedexCount / $totalCount) * 1000) / 10;
                            echo number_format($percentage, 1);
                        ?>%</strong>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?php 
                            $percentage = floor(($fedexCount / $totalCount) * 1000) / 10;
                            echo number_format($percentage, 1);
                        ?>%; background: linear-gradient(90deg, #4caf50 0%, #81c784 100%);">
                            <?php echo number_format($percentage, 1); ?>%
                        </div>
                    </div>
                </div>
                <div style="margin-top: 20px; font-size: 14px; color: #666;">
                    <strong><?= $fedexCount ?></strong> addresses can be shipped via FedEx<br>
                    <strong><?= $uspsCount ?></strong> addresses should be shipped via USPS
                </div>
            </div>
            <?php endif; ?>

            <div class="action-bar">
                <a href="address-validation.php" class="btn btn-secondary">
                    ← Validate More Addresses
                </a>
                <a href="index.php" class="btn btn-primary">
                    Go to Tracking
                </a>
                <a href="clear.php" class="btn btn-danger" onclick="return confirm('Clear all files and reset session?');">
                    🗑️ Clear All Files
                </a>
            </div>

            <div class="info-box">
                <h3>What's Next?</h3>
                <ul>
                    <li><strong>📦 FedEx List:</strong> Use this file for FedEx shipping - addresses are validated and guaranteed deliverable</li>
                    <li><strong>📊 FedEx Break Result:</strong> Shows address ranges in groups of 100. The "Helper" column shows position within each group (0-99), making it easy to process in batches</li>
                    <li><strong>📬 USPS List:</strong> Ship these addresses via USPS instead - FedEx cannot validate/deliver to these locations</li>
                </ul>
            </div>
            
            <?php include 'includes/footer.php'; ?>
