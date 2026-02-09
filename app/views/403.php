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
    <script>(function(){var t=localStorage.getItem('wp-theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');})()</script>
</head>
<body class="error-body">

<div class="error-container">
    <h1 class="error-code">403</h1>
    <p class="error-message">Zugriff verweigert</p>
    <p style="margin: 1rem 0; color: var(--color-text-secondary);">Sie haben keine Berechtigung fuer diese Aktion.</p>
    <a href="<?= Security::esc($baseUrl) ?>/?r=home" class="btn btn-primary">Zurueck zur Startseite</a>
</div>

</body>
</html>
