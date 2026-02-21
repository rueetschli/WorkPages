<?php
/**
 * Attachments partial - attachment list + upload form (AP17).
 *
 * Variables expected:
 *   $entityType  - 'page' or 'task'
 *   $entityId    - int, ID of the entity
 *   $baseUrl     - string, base URL of the app
 *
 * Loads attachments and checks permissions internally.
 */
$__attachments = Attachment::listFor($entityType, $entityId);
$__canUpload   = Authz::can(Authz::ATTACHMENT_UPLOAD);
$__canDelete   = Authz::can(Authz::ATTACHMENT_DELETE);
$__userId      = (int) ($_SESSION['user_id'] ?? 0);

// Team-based edit check for upload/delete
if ($__canUpload) {
    $__canUpload = AttachmentService::canEditEntity($__userId, $entityType, $entityId);
}
if ($__canDelete) {
    $__canDelete = AttachmentService::canEditEntity($__userId, $entityType, $entityId);
}

$__maxMb       = AttachmentService::getMaxMb();
$__allowedExts = AttachmentService::getAllowedExtDisplay();
?>
<div class="section-block attachments-section">
    <h2><?= Security::esc(t('attachments.title')) ?></h2>

    <?php if (empty($__attachments) && !$__canUpload): ?>
        <p class="placeholder-text"><?= Security::esc(t('messages.no_attachments')) ?></p>
    <?php endif; ?>

    <?php if (!empty($__attachments)): ?>
    <div class="attachments-list">
        <table class="attachments-table">
            <thead>
                <tr>
                    <th><?= Security::esc(t('labels.file')) ?></th>
                    <th><?= Security::esc(t('labels.size')) ?></th>
                    <th><?= Security::esc(t('labels.uploaded_by')) ?></th>
                    <th><?= Security::esc(t('labels.date')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($__attachments as $__att): ?>
                <tr class="attachment-row">
                    <td class="attachment-name-cell" data-label="<?= Security::esc(t('labels.file')) ?>">
                        <?php if (Attachment::isPreviewable($__att['mime_type'])): ?>
                        <span class="attachment-icon attachment-icon-image" title="<?= Security::esc(t('attachments.image')) ?>">&#128247;</span>
                        <?php elseif ($__att['mime_type'] === 'application/pdf'): ?>
                        <span class="attachment-icon attachment-icon-pdf" title="<?= Security::esc(t('attachments.pdf')) ?>">&#128196;</span>
                        <?php else: ?>
                        <span class="attachment-icon attachment-icon-file" title="<?= Security::esc(t('attachments.file')) ?>">&#128206;</span>
                        <?php endif; ?>
                        <a href="<?= Security::esc($baseUrl) ?>/?r=attachment_download&amp;id=<?= (int) $__att['id'] ?>"
                           class="attachment-link" title="<?= Security::esc(t('actions.download')) ?>">
                            <?= Security::esc($__att['original_name']) ?>
                        </a>
                        <?php if (Attachment::isPreviewable($__att['mime_type'])): ?>
                        <a href="<?= Security::esc($baseUrl) ?>/?r=attachment_download&amp;id=<?= (int) $__att['id'] ?>&amp;disposition=inline"
                           class="attachment-preview-link" target="_blank" title="<?= Security::esc(t('attachments.preview')) ?>">
                            (<?= Security::esc(t('attachments.preview')) ?>)
                        </a>
                        <?php elseif ($__att['mime_type'] === 'application/pdf'): ?>
                        <a href="<?= Security::esc($baseUrl) ?>/?r=attachment_download&amp;id=<?= (int) $__att['id'] ?>&amp;disposition=inline"
                           class="attachment-preview-link" target="_blank" title="<?= Security::esc(t('attachments.show')) ?>">
                            (<?= Security::esc(t('attachments.show')) ?>)
                        </a>
                        <?php endif; ?>
                    </td>
                    <td class="attachment-size-cell" data-label="<?= Security::esc(t('labels.size')) ?>">
                        <?= Security::esc(Attachment::formatSize((int) $__att['file_size'])) ?>
                    </td>
                    <td class="attachment-uploader-cell" data-label="<?= Security::esc(t('labels.uploaded_by')) ?>">
                        <?= Security::esc($__att['uploader_name'] ?? t('comments.unknown_author')) ?>
                    </td>
                    <td class="attachment-date-cell" data-label="<?= Security::esc(t('labels.date')) ?>">
                        <?= Security::esc(date('d.m.Y H:i', strtotime($__att['created_at']))) ?>
                    </td>
                    <td class="attachment-actions-cell">
                        <a href="<?= Security::esc($baseUrl) ?>/?r=attachment_download&amp;id=<?= (int) $__att['id'] ?>"
                           class="btn-sm btn-download" title="<?= Security::esc(t('actions.download')) ?>"><?= Security::esc(t('actions.download')) ?></a>
                        <?php if ($__canDelete): ?>
                        <form method="post"
                              action="<?= Security::esc($baseUrl) ?>/?r=attachment_delete&amp;id=<?= (int) $__att['id'] ?>"
                              class="inline-form"
                              onsubmit="return confirm('<?= Security::esc(t('messages.confirm_delete_attachment')) ?>');">
                            <?= Security::csrfField() ?>
                            <button type="submit" class="btn-sm btn-remove" title="<?= Security::esc(t('actions.delete')) ?>"><?= Security::esc(t('actions.delete')) ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($__canUpload): ?>
    <form method="post"
          action="<?= Security::esc($baseUrl) ?>/?r=attachment_upload"
          enctype="multipart/form-data"
          class="attachment-upload-form">
        <?= Security::csrfField() ?>
        <input type="hidden" name="entity_type" value="<?= Security::esc($entityType) ?>">
        <input type="hidden" name="entity_id" value="<?= (int) $entityId ?>">
        <div class="attachment-upload-row">
            <div class="attachment-file-input">
                <input type="file" name="attachment" id="attachment-file-<?= Security::esc($entityType) ?>-<?= (int) $entityId ?>"
                       class="form-input" required>
                <span class="form-hint"><?= Security::esc(t('messages.allowed_formats', ['formats' => $__allowedExts, 'max_mb' => $__maxMb])) ?></span>
            </div>
            <button type="submit" class="btn btn-primary btn-sm-pad"><?= Security::esc(t('actions.upload')) ?></button>
        </div>
    </form>
    <?php endif; ?>
</div>
