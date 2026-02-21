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

// AP24: Available languages for login switcher
$__loginLangs = I18nService::listAvailableLanguages();
$__loginCurrentLang = I18nService::getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?= Security::esc(I18nService::getCurrentLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Security::esc(t('actions.login')) ?> - <?= Security::esc($appName) ?></title>
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
        <p class="login-subtitle"><?= Security::esc(t('messages.login_subtitle')) ?></p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= Security::esc($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=login" class="login-form">
            <?= Security::csrfField() ?>

            <div class="form-group">
                <label for="email"><?= Security::esc(t('labels.email')) ?></label>
                <input type="email" id="email" name="email" required autofocus
                       value="<?= $emailValue ?>"
                       placeholder="<?= Security::esc(t('placeholders.email')) ?>" class="form-input">
            </div>

            <div class="form-group">
                <label for="password"><?= Security::esc(t('labels.password')) ?></label>
                <input type="password" id="password" name="password" required
                       placeholder="<?= Security::esc(t('placeholders.password')) ?>" class="form-input">
            </div>

            <button type="submit" class="btn btn-primary btn-block"><?= Security::esc(t('actions.login')) ?></button>
        </form>

        <?php if (count($__loginLangs) > 1): ?>
        <div class="login-lang-switch">
            <?php foreach ($__loginLangs as $__ll): ?>
                <?php if ($__ll['code'] === $__loginCurrentLang): ?>
                    <span class="login-lang-active"><?= Security::esc(I18nService::languageName($__ll['code'])) ?></span>
                <?php else: ?>
                    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=language_switch" class="inline-form">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="language" value="<?= Security::esc($__ll['code']) ?>">
                        <button type="submit" class="login-lang-btn"><?= Security::esc(I18nService::languageName($__ll['code'])) ?></button>
                    </form>
                <?php endif; ?>
                <?php if ($__ll !== end($__loginLangs)): ?>
                    <span class="login-lang-sep">|</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
