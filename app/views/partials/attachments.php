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
    <h2>Anhaenge</h2>

    <?php if (empty($__attachments) && !$__canUpload): ?>
        <p class="placeholder-text">Keine Anhaenge vorhanden.</p>
    <?php endif; ?>

    <?php if (!empty($__attachments)): ?>
    <div class="attachments-list">
        <table class="attachments-table">
            <thead>
                <tr>
                    <th>Datei</th>
                    <th>Groesse</th>
                    <th>Hochgeladen von</th>
                    <th>Datum</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($__attachments as $__att): ?>
                <tr class="attachment-row">
                    <td class="attachment-name-cell" data-label="Datei">
                        <?php if (Attachment::isPreviewable($__att['mime_type'])): ?>
                        <span class="attachment-icon attachment-icon-image" title="Bild">&#128247;</span>
                        <?php elseif ($__att['mime_type'] === 'application/pdf'): ?>
                        <span class="attachment-icon attachment-icon-pdf" title="PDF">&#128196;</span>
                        <?php else: ?>
                        <span class="attachment-icon attachment-icon-file" title="Datei">&#128206;</span>
                        <?php endif; ?>
                        <a href="<?= Security::esc($baseUrl) ?>/?r=attachment_download&amp;id=<?= (int) $__att['id'] ?>"
                           class="attachment-link" title="Herunterladen">
                            <?= Security::esc($__att['original_name']) ?>
                        </a>
                        <?php if (Attachment::isPreviewable($__att['mime_type'])): ?>
                        <a href="<?= Security::esc($baseUrl) ?>/?r=attachment_download&amp;id=<?= (int) $__att['id'] ?>&amp;disposition=inline"
                           class="attachment-preview-link" target="_blank" title="Vorschau">
                            (Vorschau)
                        </a>
                        <?php elseif ($__att['mime_type'] === 'application/pdf'): ?>
                        <a href="<?= Security::esc($baseUrl) ?>/?r=attachment_download&amp;id=<?= (int) $__att['id'] ?>&amp;disposition=inline"
                           class="attachment-preview-link" target="_blank" title="Anzeigen">
                            (Anzeigen)
                        </a>
                        <?php endif; ?>
                    </td>
                    <td class="attachment-size-cell" data-label="Groesse">
                        <?= Security::esc(Attachment::formatSize((int) $__att['file_size'])) ?>
                    </td>
                    <td class="attachment-uploader-cell" data-label="Von">
                        <?= Security::esc($__att['uploader_name'] ?? 'Unbekannt') ?>
                    </td>
                    <td class="attachment-date-cell" data-label="Datum">
                        <?= Security::esc(date('d.m.Y H:i', strtotime($__att['created_at']))) ?>
                    </td>
                    <td class="attachment-actions-cell">
                        <a href="<?= Security::esc($baseUrl) ?>/?r=attachment_download&amp;id=<?= (int) $__att['id'] ?>"
                           class="btn-sm btn-download" title="Herunterladen">Download</a>
                        <?php if ($__canDelete): ?>
                        <form method="post"
                              action="<?= Security::esc($baseUrl) ?>/?r=attachment_delete&amp;id=<?= (int) $__att['id'] ?>"
                              class="inline-form"
                              onsubmit="return confirm('Anhang wirklich loeschen?');">
                            <?= Security::csrfField() ?>
                            <button type="submit" class="btn-sm btn-remove" title="Loeschen">Loeschen</button>
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
                <span class="form-hint">Erlaubt: <?= Security::esc($__allowedExts) ?> (max. <?= (int) $__maxMb ?> MB)</span>
            </div>
            <button type="submit" class="btn btn-primary btn-sm-pad">Hochladen</button>
        </div>
    </form>
    <?php endif; ?>
</div>
