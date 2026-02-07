<?php
/**
 * Login view - standalone layout (no sidebar).
 */
$appName = $GLOBALS['config']['APP_NAME'] ?? 'Work Pages';
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= Security::esc($appName) ?></title>
    <link rel="stylesheet" href="<?= Security::esc($baseUrl) ?>/assets/app.css">
</head>
<body class="login-body">

<div class="login-container">
    <div class="login-card">
        <h1 class="login-title"><?= Security::esc($appName) ?></h1>
        <p class="login-subtitle">Sign in to your workspace</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= Security::esc($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=login" class="login-form">
            <?= Security::csrfField() ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus
                       placeholder="you@company.com" class="form-input">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       placeholder="Your password" class="form-input">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        <p class="login-footer-note">Authentication will be implemented in AP2.</p>
    </div>
</div>

</body>
</html>
