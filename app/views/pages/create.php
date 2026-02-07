<?php
/**
 * Page create form.
 * Variables: $error (string|null), $formData (array), $parentPages (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <h1>Neue Seite</h1>
    <p class="subtitle">Erstelle eine neue Wissens- oder Arbeitsseite.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<div class="section-block">
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=page_create" class="page-form">
        <?= Security::csrfField() ?>

        <div class="form-group">
            <label for="title">Titel</label>
            <input type="text" id="title" name="title" class="form-input"
                   value="<?= Security::esc($formData['title']) ?>"
                   required autofocus>
        </div>

        <div class="form-group">
            <label for="parent_id">Uebergeordnete Seite (optional)</label>
            <select id="parent_id" name="parent_id" class="form-input">
                <option value="">-- Keine --</option>
                <?php foreach ($parentPages as $pp): ?>
                    <option value="<?= (int) $pp['id'] ?>"
                        <?= (string) $formData['parent_id'] === (string) $pp['id'] ? 'selected' : '' ?>>
                        <?= Security::esc($pp['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="content_md">Inhalt (Markdown)</label>
            <textarea id="content_md" name="content_md" class="form-input form-textarea"
                      rows="18"><?= Security::esc($formData['content_md']) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Seite erstellen</button>
            <a href="<?= Security::esc($baseUrl) ?>/?r=pages" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
