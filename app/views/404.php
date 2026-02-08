<?php
/**
 * 404 - Seite nicht gefunden.
 */
$appName = $GLOBALS['config']['APP_NAME'] ?? 'WorkPages';
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - <?= Security::esc($appName) ?></title>
    <link rel="stylesheet" href="<?= Security::esc($baseUrl) ?>/assets/app.css">
</head>
<body class="error-body">
    <div class="error-container">
        <h1 class="error-code">404</h1>
        <p class="error-message">Seite nicht gefunden.</p>
        <p style="margin: 1rem 0; color: #636e72;">Die angeforderte Seite existiert nicht oder wurde verschoben.</p>
        <a href="<?= Security::esc($baseUrl) ?>/?r=home" class="btn btn-primary">Zurueck zur Startseite</a>
    </div>
</body>
</html>
