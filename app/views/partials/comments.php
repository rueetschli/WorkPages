<?php
/**
 * Comments partial - comment list + comment form.
 *
 * Variables expected:
 *   $comments    - array of comment rows (from Comment::listFor)
 *   $entityType  - 'page' or 'task'
 *   $entityId    - int, ID of the entity
 *   $canEdit     - bool, whether the user can write comments
 *   $flashError  - string|null, validation error from session flash
 *   $baseUrl     - string, base URL of the app
 */
?>
<div class="section-block comments-section">
    <h2>Kommentare</h2>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-error"><?= Security::esc($flashError) ?></div>
    <?php endif; ?>

    <?php if (empty($comments)): ?>
        <p class="placeholder-text">Noch keine Kommentare.</p>
    <?php else: ?>
        <div class="comments-list">
            <?php foreach ($comments as $comment): ?>
            <div class="comment-item" id="comment-<?= (int) $comment['id'] ?>">
                <div class="comment-header">
                    <span class="comment-author"><?= Security::esc($comment['author_name'] ?? 'Unbekannt') ?></span>
                    <span class="comment-date"><?= Security::esc(date('d.m.Y H:i', strtotime($comment['created_at']))) ?></span>
                    <?php if ($canEdit): ?>
                    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=comment_delete&amp;id=<?= (int) $comment['id'] ?>"
                          class="inline-form comment-delete-form" onsubmit="return confirm('Kommentar wirklich loeschen?');">
                        <?= Security::csrfField() ?>
                        <button type="submit" class="btn-comment-delete" title="Loeschen">&times;</button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="comment-body md-content">
                    <?= Markdown::render($comment['body_md']) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($canEdit): ?>
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=comment_create" class="comment-form">
        <?= Security::csrfField() ?>
        <input type="hidden" name="entity_type" value="<?= Security::esc($entityType) ?>">
        <input type="hidden" name="entity_id" value="<?= (int) $entityId ?>">
        <div class="form-group">
            <label for="comment-body">Neuer Kommentar</label>
            <textarea id="comment-body" name="body_md" class="form-input form-textarea comment-textarea"
                      placeholder="Kommentar schreiben (Markdown wird unterstuetzt)..."
                      required maxlength="<?= Comment::MAX_BODY_LENGTH ?>"></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Kommentar speichern</button>
        </div>
    </form>
    <?php endif; ?>
</div>
