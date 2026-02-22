<?php
/**
 * Admin backup & operations guidance view (AP29).
 *
 * Variables:
 *   $lastBackupAt   - string|null  (datetime or null)
 *   $lastBackupNote - string
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<style>
.backup-sections { display: flex; flex-direction: column; gap: var(--sp-4, 1rem); }
.backup-card {
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 8px;
    background: var(--bg-card, #fff);
    overflow: hidden;
}
.backup-card-header {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.85rem 1rem;
    font-weight: 600; font-size: 0.95rem;
    border-bottom: 1px solid var(--border, #e2e8f0);
    background: var(--bg-card, #fff);
}
.backup-card-header-icon {
    display: flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px; flex-shrink: 0;
}
.backup-card-header-icon svg { width: 16px; height: 16px; }
.backup-card-header-icon--blue { background: #dbeafe; color: #2563eb; }
.backup-card-header-icon--green { background: #dcfce7; color: #16a34a; }
.backup-card-header-icon--amber { background: #fef3c7; color: #d97706; }
.backup-card-header-icon--purple { background: #f3e8ff; color: #7c3aed; }
.backup-card-body { padding: 1rem; }
.backup-checklist { list-style: none; padding: 0; margin: 0; }
.backup-checklist li {
    display: flex; align-items: flex-start; gap: 0.6rem;
    padding: 0.5rem 0;
    font-size: 0.9rem;
    line-height: 1.5;
}
.backup-checklist li + li { border-top: 1px solid var(--border-light, #f1f5f9); }
.backup-check-icon {
    flex-shrink: 0; width: 20px; height: 20px; margin-top: 1px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%; border: 2px solid var(--border, #cbd5e1);
    color: var(--text-muted, #64748b); font-size: 0.7rem;
}
.backup-checklist li .backup-check-text { flex: 1; }
.backup-checklist li .backup-check-hint {
    font-size: 0.82rem; color: var(--text-muted, #64748b); margin-top: 0.15rem;
}
.backup-links { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-light, #f1f5f9); }
.backup-form-row { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; }
.backup-form-group { display: flex; flex-direction: column; gap: 0.3rem; }
.backup-form-group label { font-size: 0.82rem; font-weight: 600; color: var(--text-muted, #64748b); }
.backup-form-group input { font-size: 0.9rem; padding: 0.4rem 0.6rem; border: 1px solid var(--border, #cbd5e1); border-radius: 6px; background: var(--bg-input, #fff); color: var(--text, #1e293b); }
.backup-form-group input:focus { outline: none; border-color: var(--primary, #2563eb); box-shadow: 0 0 0 2px rgba(37,99,235,0.15); }
.backup-last-info {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem;
    font-size: 0.9rem;
}
.backup-last-info--set { background: #dcfce7; color: #15803d; }
.backup-last-info--unset { background: #fef3c7; color: #92400e; }
.backup-last-info svg { width: 18px; height: 18px; flex-shrink: 0; }
</style>

<div class="page-header">
    <div class="page-header-row">
        <h1><?= Security::esc(t('backup.page_title')) ?></h1>
    </div>
    <p class="text-muted"><?= Security::esc(t('backup.page_subtitle')) ?></p>
</div>

<!-- Last Backup Status -->
<?php if ($lastBackupAt): ?>
<div class="backup-last-info backup-last-info--set">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    <span>
        <?= Security::esc(t('backup.last_backup_label')) ?>:
        <strong><?= Security::esc(date('d.m.Y H:i', strtotime($lastBackupAt))) ?></strong>
        <?php if ($lastBackupNote !== ''): ?>
            &mdash; <?= Security::esc($lastBackupNote) ?>
        <?php endif; ?>
    </span>
</div>
<?php else: ?>
<div class="backup-last-info backup-last-info--unset">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span><?= Security::esc(t('backup.no_backup_recorded')) ?></span>
</div>
<?php endif; ?>

<div class="backup-sections">

    <!-- Section A: Backup Essentials -->
    <div class="backup-card">
        <div class="backup-card-header">
            <span class="backup-card-header-icon backup-card-header-icon--blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            </span>
            <?= Security::esc(t('backup.section_backup')) ?>
        </div>
        <div class="backup-card-body">
            <ul class="backup-checklist">
                <li>
                    <span class="backup-check-icon">1</span>
                    <div class="backup-check-text">
                        <strong><?= Security::esc(t('backup.check_db')) ?></strong>
                        <div class="backup-check-hint"><?= Security::esc(t('backup.check_db_hint')) ?></div>
                    </div>
                </li>
                <li>
                    <span class="backup-check-icon">2</span>
                    <div class="backup-check-text">
                        <strong><?= Security::esc(t('backup.check_storage')) ?></strong>
                        <div class="backup-check-hint"><?= Security::esc(t('backup.check_storage_hint')) ?></div>
                    </div>
                </li>
                <li>
                    <span class="backup-check-icon">3</span>
                    <div class="backup-check-text">
                        <strong><?= Security::esc(t('backup.check_config')) ?></strong>
                        <div class="backup-check-hint"><?= Security::esc(t('backup.check_config_hint')) ?></div>
                    </div>
                </li>
            </ul>
            <div class="backup-links">
                <a href="https://github.com/rueetschli/WorkPages/blob/main/docs/OPERATOR_GUIDE.md#5-backup" target="_blank" rel="noopener" class="btn btn-secondary btn-sm-pad">
                    <?= Security::esc(t('backup.link_operator_guide')) ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Section B: Restore Essentials -->
    <div class="backup-card">
        <div class="backup-card-header">
            <span class="backup-card-header-icon backup-card-header-icon--green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
            </span>
            <?= Security::esc(t('backup.section_restore')) ?>
        </div>
        <div class="backup-card-body">
            <ul class="backup-checklist">
                <li>
                    <span class="backup-check-icon">1</span>
                    <div class="backup-check-text">
                        <strong><?= Security::esc(t('backup.restore_db')) ?></strong>
                        <div class="backup-check-hint"><?= Security::esc(t('backup.restore_db_hint')) ?></div>
                    </div>
                </li>
                <li>
                    <span class="backup-check-icon">2</span>
                    <div class="backup-check-text">
                        <strong><?= Security::esc(t('backup.restore_storage')) ?></strong>
                        <div class="backup-check-hint"><?= Security::esc(t('backup.restore_storage_hint')) ?></div>
                    </div>
                </li>
                <li>
                    <span class="backup-check-icon">3</span>
                    <div class="backup-check-text">
                        <strong><?= Security::esc(t('backup.restore_smoke')) ?></strong>
                        <div class="backup-check-hint"><?= Security::esc(t('backup.restore_smoke_hint')) ?></div>
                    </div>
                </li>
            </ul>
        </div>
    </div>

    <!-- Section C: Operations -->
    <div class="backup-card">
        <div class="backup-card-header">
            <span class="backup-card-header-icon backup-card-header-icon--amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            </span>
            <?= Security::esc(t('backup.section_operations')) ?>
        </div>
        <div class="backup-card-body">
            <ul class="backup-checklist">
                <li>
                    <span class="backup-check-icon">1</span>
                    <div class="backup-check-text">
                        <strong><?= Security::esc(t('backup.ops_update')) ?></strong>
                        <div class="backup-check-hint"><?= Security::esc(t('backup.ops_update_hint')) ?></div>
                    </div>
                </li>
                <li>
                    <span class="backup-check-icon">2</span>
                    <div class="backup-check-text">
                        <strong><?= Security::esc(t('backup.ops_migrations')) ?></strong>
                        <div class="backup-check-hint"><?= Security::esc(t('backup.ops_migrations_hint')) ?></div>
                    </div>
                </li>
                <li>
                    <span class="backup-check-icon">3</span>
                    <div class="backup-check-text">
                        <strong><?= Security::esc(t('backup.ops_health')) ?></strong>
                        <div class="backup-check-hint"><?= Security::esc(t('backup.ops_health_hint')) ?></div>
                    </div>
                </li>
            </ul>
            <div class="backup-links">
                <a href="<?= Security::esc($baseUrl) ?>/?r=admin_health" class="btn btn-secondary btn-sm-pad">
                    <?= Security::esc(t('nav.admin.health')) ?>
                </a>
                <a href="<?= Security::esc($baseUrl) ?>/?r=admin_migrate" class="btn btn-secondary btn-sm-pad">
                    <?= Security::esc(t('nav.admin.migrations')) ?>
                </a>
                <a href="<?= Security::esc($baseUrl) ?>/?r=admin_languages" class="btn btn-secondary btn-sm-pad">
                    <?= Security::esc(t('nav.admin.languages')) ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Section D: Record Last Backup -->
    <div class="backup-card">
        <div class="backup-card-header">
            <span class="backup-card-header-icon backup-card-header-icon--purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </span>
            <?= Security::esc(t('backup.section_record')) ?>
        </div>
        <div class="backup-card-body">
            <p style="font-size:0.88rem;color:var(--text-muted,#64748b);margin:0 0 0.75rem;">
                <?= Security::esc(t('backup.record_description')) ?>
            </p>
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_backup_save">
                <?= Security::csrfField() ?>
                <div class="backup-form-row">
                    <div class="backup-form-group">
                        <label for="last_backup_at"><?= Security::esc(t('backup.field_date')) ?></label>
                        <input type="datetime-local" id="last_backup_at" name="last_backup_at"
                               value="<?= $lastBackupAt ? Security::esc(date('Y-m-d\TH:i', strtotime($lastBackupAt))) : '' ?>"
                               style="min-width:200px;">
                    </div>
                    <div class="backup-form-group" style="flex:1;min-width:200px;">
                        <label for="last_backup_note"><?= Security::esc(t('backup.field_note')) ?></label>
                        <input type="text" id="last_backup_note" name="last_backup_note"
                               value="<?= Security::esc($lastBackupNote) ?>"
                               placeholder="<?= Security::esc(t('backup.field_note_placeholder')) ?>"
                               maxlength="255">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm-pad"><?= Security::esc(t('actions.save')) ?></button>
                </div>
            </form>
        </div>
    </div>

</div>
