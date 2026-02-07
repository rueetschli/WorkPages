<?php
/**
 * Login view - standalone layout (no sidebar).
 *
 * Variables expected:
 *   $error     - string|null, error message to display
 *   $pageTitle - string, page title
 */
$appName = $GLOBALS['config']['APP_NAME'] ?? 'Work Pages';
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$emailValue = Security::esc(trim($_POST['email'] ?? ''));
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
        <p class="login-subtitle">Anmelden</p>

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
