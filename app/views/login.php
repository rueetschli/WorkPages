<?php
/**
 * Login view - standalone layout (no sidebar).
 *
 * Variables expected:
 *   $error     - string|null, error message to display
 *   $pageTitle - string, page title
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$emailValue = Security::esc(trim($_POST['email'] ?? ''));

// AP20: Use system settings for branding
$__loginLogoUrl = '';
$__loginThemeCss = '';
$__loginMaintenance = false;
$__loginMaintMsg = '';
$__loginMaintLevel = 'info';
try {
    $appName = SystemSettingsService::companyName();
    $__loginLogoUrl = SystemSettingsService::logoUrl();
    $__loginThemeCss = ThemeService::renderCssVariables();
    $__loginMaintenance = SystemSettingsService::isMaintenanceActive();
    $__loginMaintMsg = SystemSettingsService::value('maintenance_message', '');
    $__loginMaintLevel = SystemSettingsService::value('maintenance_level', 'info');
} catch (Throwable $e) {
    $appName = $GLOBALS['config']['APP_NAME'] ?? 'WorkPages';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= Security::esc($appName) ?></title>
    <link rel="stylesheet" href="<?= Security::esc($baseUrl) ?>/assets/app.css">
    <?= $__loginThemeCss ?>
    <script>
    (function() {
        var t = localStorage.getItem('wp-theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    })();
    </script>
</head>
<body class="login-body">

<?php if ($__loginMaintenance && $__loginMaintMsg !== ''): ?>
<div class="maintenance-banner maintenance-<?= Security::esc($__loginMaintLevel) ?>">
    <?= Security::esc($__loginMaintMsg) ?>
</div>
<?php endif; ?>

<div class="login-container">
    <div class="login-card">
        <?php if ($__loginLogoUrl !== ''): ?>
            <img src="<?= Security::esc($__loginLogoUrl) ?>" alt="<?= Security::esc($appName) ?>" class="login-logo">
        <?php else: ?>
            <h1 class="login-title"><?= Security::esc($appName) ?></h1>
        <?php endif; ?>
        <p class="login-subtitle">Melden Sie sich an, um fortzufahren</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= Security::esc($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=login" class="login-form">
            <?= Security::csrfField() ?>

            <div class="form-group">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email" required autofocus
                       value="<?= $emailValue ?>"
                       placeholder="name@firma.ch" class="form-input">
            </div>

            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required
                       placeholder="Ihr Passwort" class="form-input">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Anmelden</button>
        </form>
    </div>
</div>

</body>
</html>
