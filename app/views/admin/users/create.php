<?php
/**
 * Admin: Create user form.
 * Variables: $error (string|null), $formData (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <li class="breadcrumb-item">
            <a href="<?= Security::esc($baseUrl) ?>/?r=admin_users">Benutzerverwaltung</a>
        </li>
        <li class="breadcrumb-item breadcrumb-current">
            <span>Neuer Benutzer</span>
        </li>
    </ol>
</nav>

<div class="page-header">
    <h1>Neuer Benutzer</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_user_store" class="form-container">
    <?= Security::csrfField() ?>

    <div class="form-group">
        <label for="email">E-Mail <span class="required">*</span></label>
        <input type="email" id="email" name="email" required
               value="<?= Security::esc($formData['email']) ?>"
               placeholder="name@firma.ch" class="form-input">
    </div>

    <div class="form-group">
        <label for="name">Name <span class="required">*</span></label>
        <input type="text" id="name" name="name" required
               value="<?= Security::esc($formData['name']) ?>"
               placeholder="Vor- und Nachname" class="form-input">
    </div>

    <div class="form-group">
        <label for="role">Rolle <span class="required">*</span></label>
        <select id="role" name="role" class="form-input">
            <?php foreach (User::ROLES as $role): ?>
                <option value="<?= Security::esc($role) ?>"
                    <?= $formData['role'] === $role ? 'selected' : '' ?>>
                    <?= Security::esc($role) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="password">Passwort <span class="required">*</span></label>
        <input type="password" id="password" name="password" required
               placeholder="Mindestens 10 Zeichen" class="form-input" minlength="10">
    </div>

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="is_active" value="1"
                <?= (int) $formData['is_active'] === 1 ? 'checked' : '' ?>>
            Benutzer ist aktiv
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Benutzer erstellen</button>
        <a href="<?= Security::esc($baseUrl) ?>/?r=admin_users" class="btn btn-secondary">Abbrechen</a>
    </div>
</form>
