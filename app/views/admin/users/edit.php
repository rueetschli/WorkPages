<?php
/**
 * Admin: Edit user form.
 * Variables: $error (string|null), $formData (array), $user (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <li class="breadcrumb-item">
            <a href="<?= Security::esc($baseUrl) ?>/?r=admin_users">Benutzerverwaltung</a>
        </li>
        <li class="breadcrumb-item breadcrumb-current">
            <span><?= Security::esc($user['name']) ?></span>
        </li>
    </ol>
</nav>

<div class="page-header">
    <h1>Benutzer bearbeiten</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_user_update&amp;id=<?= (int) $user['id'] ?>" class="form-container">
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
        <label for="password">Passwort</label>
        <input type="password" id="password" name="password"
               placeholder="Leer lassen um Passwort nicht zu aendern" class="form-input" minlength="10">
        <small class="form-hint">Mindestens 10 Zeichen. Leer lassen = keine Aenderung.</small>
    </div>

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="is_active" value="1"
                <?= (int) $formData['is_active'] === 1 ? 'checked' : '' ?>>
            Benutzer ist aktiv
        </label>
    </div>

    <div class="page-meta" style="margin-bottom: var(--space-lg);">
        <span class="text-muted">
            Erstellt am <?= Security::esc(date('d.m.Y H:i', strtotime($user['created_at']))) ?>
            <?php if ($user['last_login_at']): ?>
                &middot; Letzter Login: <?= Security::esc(date('d.m.Y H:i', strtotime($user['last_login_at']))) ?>
            <?php endif; ?>
        </span>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="<?= Security::esc($baseUrl) ?>/?r=admin_users" class="btn btn-secondary">Abbrechen</a>
    </div>
</form>
