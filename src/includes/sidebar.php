<!-- Sidebar Navigation -->
<aside class="sidebar">
    <nav class="sidebar-nav">
        <!-- Main Features -->
        <div class="nav-section">
            <div class="nav-section-title">Main Features</div>
            <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <span class="nav-item-icon">📦</span>
                <span class="nav-item-text">Tracking & POD</span>
            </a>
            <a href="address-validation.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'address-validation.php' ? 'active' : ''; ?>">
                <span class="nav-item-icon">📍</span>
                <span class="nav-item-text">Address Validation</span>
            </a>
        </div>

        <!-- Results & Processing -->
        <div class="nav-section">
            <div class="nav-section-title">Processing</div>
            <a href="process.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'process.php' ? 'active' : ''; ?>">
                <span class="nav-item-icon">⚙️</span>
                <span class="nav-item-text">Processing Status</span>
            </a>
            <a href="results.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'results.php' ? 'active' : ''; ?>">
                <span class="nav-item-icon">📊</span>
                <span class="nav-item-text">Tracking Results</span>
            </a>
            <a href="results-address.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'results-address.php' ? 'active' : ''; ?>">
                <span class="nav-item-icon">✅</span>
                <span class="nav-item-text">Validation Results</span>
            </a>
        </div>

        <!-- Tools & Settings -->
        <div class="nav-section">
            <div class="nav-section-title">Tools</div>
            <a href="view-logs.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'view-logs.php' ? 'active' : ''; ?>">
                <span class="nav-item-icon">🔍</span>
                <span class="nav-item-text">Debug Logs</span>
            </a>
            <a href="clear.php" class="nav-item" onclick="return confirm('Clear all files and reset session?');">
                <span class="nav-item-icon">🗑️</span>
                <span class="nav-item-text">Clear All Files</span>
            </a>
        </div>
    </nav>
</aside>
