<?php
/**
 * 403 Forbidden error page.
 */
$appName = $GLOBALS['config']['APP_NAME'] ?? 'Work Pages';
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zugriff verweigert - <?= Security::esc($appName) ?></title>
    <link rel="stylesheet" href="<?= Security::esc($baseUrl) ?>/assets/app.css">
</head>
<body class="login-body">

<div class="login-container">
    <div class="login-card" style="text-align: center;">
        <h1 style="font-size: 3rem; margin-bottom: 0.5rem;">403</h1>
        <p class="login-subtitle">Zugriff verweigert</p>
        <p style="margin: 1rem 0;">Sie haben keine Berechtigung fuer diese Aktion.</p>
        <a href="<?= Security::esc($baseUrl) ?>/?r=home" class="btn btn-primary">Zurueck zur Startseite</a>
    </div>
</div>

</body>
</html>
