<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'FedEx Tracker'; ?></title>
    <link rel="stylesheet" href="css/common.css">
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <h1>
                <span>🚚</span>
                <span>FedEx Tracker Suite</span>
            </h1>
            <div class="header-info">
                <div class="user-info">
                    <span>👤</span>
                    <span><?php echo htmlspecialchars(VALID_USER); ?></span>
                </div>
            </div>
        </div>
    </header>
