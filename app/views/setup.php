<?php
/**
 * Setup view - create initial admin user (standalone layout).
 *
 * Variables expected:
 *   $error   - string|null
 *   $success - string|null
 */
$appName = $GLOBALS['config']['APP_NAME'] ?? 'Work Pages';
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - <?= Security::esc($appName) ?></title>
    <link rel="stylesheet" href="<?= Security::esc($baseUrl) ?>/assets/app.css">
    <script>(function(){var t=localStorage.getItem('wp-theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');})()</script>
</head>
<body class="login-body">

<div class="login-container">
    <div class="login-card">
        <h1 class="login-title"><?= Security::esc($appName) ?></h1>
        <p class="login-subtitle">Ersteinrichtung - Admin-Benutzer anlegen</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= Security::esc($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= Security::esc($success) ?></div>
            <p style="text-align:center; margin-top: var(--sp-4);">
                <a href="<?= Security::esc($baseUrl) ?>/?r=login" class="btn btn-primary">Zum Login</a>
            </p>
        <?php else: ?>
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=setup" class="login-form">
                <?= Security::csrfField() ?>

                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required autofocus
                           value="<?= Security::esc(trim($_POST['name'] ?? '')) ?>"
                           placeholder="Admin" class="form-input">
                </div>

                <div class="form-group">
                    <label for="email">E-Mail</label>
                    <input type="email" id="email" name="email" required
                           value="<?= Security::esc(trim($_POST['email'] ?? '')) ?>"
                           placeholder="admin@example.com" class="form-input">
                </div>

                <div class="form-group">
                    <label for="password">Passwort</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Mindestens 6 Zeichen" class="form-input">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Admin erstellen</button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
