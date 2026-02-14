<?php
/**
 * Task edit form.
 * Variables: $task (array), $error (string|null), $formData (array), $users (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <h1>Aufgabe bearbeiten</h1>
    <p class="subtitle"><?= Security::esc($task['title']) ?></p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<div class="section-block">
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=task_edit&amp;id=<?= (int) $task['id'] ?>" class="page-form">
        <?= Security::csrfField() ?>

        <div class="form-group">
            <label for="title">Titel</label>
            <input type="text" id="title" name="title" class="form-input"
                   value="<?= Security::esc($formData['title']) ?>"
                   required autofocus>
        </div>

        <div class="form-row">
            <div class="form-group form-group-half">
                <label for="column_id">Spalte</label>
                <select id="column_id" name="column_id" class="form-input">
                    <?php foreach ($boardColumns as $col): ?>
                        <option value="<?= (int) $col['id'] ?>"
                            <?= (string) $formData['column_id'] === (string) $col['id'] ? 'selected' : '' ?>>
                            <?= Security::esc($col['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group form-group-half">
                <label for="owner_id">Owner</label>
                <select id="owner_id" name="owner_id" class="form-input">
                    <option value="">-- Nicht zugewiesen --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"
                            <?= (string) $formData['owner_id'] === (string) $u['id'] ? 'selected' : '' ?>>
                            <?= Security::esc($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group form-group-half">
                <label for="due_date">Faelligkeitsdatum</label>
                <input type="date" id="due_date" name="due_date" class="form-input"
                       value="<?= Security::esc($formData['due_date']) ?>">
            </div>

            <div class="form-group form-group-half">
                <label for="tags">Tags (kommagetrennt)</label>
                <input type="text" id="tags" name="tags" class="form-input"
                       value="<?= Security::esc($formData['tags']) ?>"
                       placeholder="z.B. dringend, marketing, kundenprojekt">
            </div>
        </div>

        <div class="form-group">
            <label for="description_md">Beschreibung (Markdown)</label>
            <textarea id="description_md" name="description_md" class="form-input form-textarea"
                      rows="14"><?= Security::esc($formData['description_md']) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Aenderungen speichern</button>
            <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&amp;id=<?= (int) $task['id'] ?>" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
