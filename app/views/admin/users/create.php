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
            <a href="<?= Security::esc($baseUrl) ?>/?r=admin_users"><?= Security::esc(t('admin.users_title')) ?></a>
        </li>
        <li class="breadcrumb-item breadcrumb-current">
            <span><?= Security::esc(t('admin.user_create')) ?></span>
        </li>
    </ol>
</nav>

<div class="page-header">
    <h1><?= Security::esc(t('admin.user_create')) ?></h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_user_store" class="form-container">
    <?= Security::csrfField() ?>

    <div class="form-group">
        <label for="email"><?= Security::esc(t('labels.email')) ?> <span class="required">*</span></label>
        <input type="email" id="email" name="email" required
               value="<?= Security::esc($formData['email']) ?>"
               placeholder="<?= Security::esc(t('placeholders.email')) ?>" class="form-input">
    </div>

    <div class="form-group">
        <label for="name"><?= Security::esc(t('labels.name')) ?> <span class="required">*</span></label>
        <input type="text" id="name" name="name" required
               value="<?= Security::esc($formData['name']) ?>"
               placeholder="<?= Security::esc(t('placeholders.name')) ?>" class="form-input">
    </div>

    <div class="form-group">
        <label for="role"><?= Security::esc(t('labels.role')) ?> <span class="required">*</span></label>
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
        <label for="password"><?= Security::esc(t('labels.password')) ?> <span class="required">*</span></label>
        <input type="password" id="password" name="password" required
               placeholder="<?= Security::esc(t('placeholders.password_new')) ?>" class="form-input" minlength="10">
    </div>

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="is_active" value="1"
                <?= (int) $formData['is_active'] === 1 ? 'checked' : '' ?>>
            <?= Security::esc(t('messages.user_is_active')) ?>
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= Security::esc(t('admin.user_create_button')) ?></button>
        <a href="<?= Security::esc($baseUrl) ?>/?r=admin_users" class="btn btn-secondary"><?= Security::esc(t('actions.cancel')) ?></a>
    </div>
</form>
