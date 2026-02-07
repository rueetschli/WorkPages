<?php
/**
 * 500 - Internal server error.
 */
$appName = $GLOBALS['config']['APP_NAME'] ?? 'Work Pages';
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - <?= Security::esc($appName) ?></title>
    <link rel="stylesheet" href="<?= Security::esc($baseUrl) ?>/assets/app.css">
</head>
<body class="error-body">
    <div class="error-container">
        <h1 class="error-code">500</h1>
        <p class="error-message">Something went wrong. Please try again later.</p>
        <a href="<?= Security::esc($baseUrl) ?>/?r=home" class="btn btn-primary">Back to Home</a>
    </div>
</body>
</html>
