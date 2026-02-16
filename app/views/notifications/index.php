<?php
/**
 * Notifications list view (AP15).
 * Variables: $notifications (array), $unreadCount (int), $filter (string)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <div class="page-header-row">
        <h1>Benachrichtigungen</h1>
        <?php if ($unreadCount > 0): ?>
        <div class="page-actions">
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=notifications_read_all" class="inline-form">
                <?= Security::csrfField() ?>
                <button type="submit" class="btn btn-secondary">Alle als gelesen markieren</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="notif-filter-tabs">
    <a href="<?= Security::esc($baseUrl) ?>/?r=notifications"
       class="notif-tab <?= $filter === 'all' ? 'notif-tab-active' : '' ?>">Alle</a>
    <a href="<?= Security::esc($baseUrl) ?>/?r=notifications&amp;filter=unread"
       class="notif-tab <?= $filter === 'unread' ? 'notif-tab-active' : '' ?>">
        Ungelesen
        <?php if ($unreadCount > 0): ?>
            <span class="notif-tab-badge"><?= (int) $unreadCount ?></span>
        <?php endif; ?>
    </a>
</div>

<?php if (empty($notifications)): ?>
    <div class="section-block">
        <p class="placeholder-text">
            <?= $filter === 'unread' ? 'Keine ungelesenen Benachrichtigungen.' : 'Keine Benachrichtigungen vorhanden.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="notif-list">
        <?php foreach ($notifications as $n): ?>
            <?php
                $isUnread = (int) $n['is_read'] === 0;
                $typeClass = '';
                $type = $n['type'] ?? '';
                if (str_contains($type, 'mention') || str_contains($type, 'assigned')) {
                    $typeClass = 'notif-item-high';
                } elseif (str_contains($type, 'comment')) {
                    $typeClass = 'notif-item-medium';
                }
            ?>
            <div class="notif-item <?= $isUnread ? 'notif-item-unread' : '' ?> <?= $typeClass ?>">
                <div class="notif-item-content">
                    <a href="<?= Security::esc($n['url']) ?>" class="notif-item-link">
                        <strong class="notif-item-title"><?= Security::esc($n['title']) ?></strong>
                        <?php if (!empty($n['body'])): ?>
                            <span class="notif-item-body"><?= Security::esc($n['body']) ?></span>
                        <?php endif; ?>
                    </a>
                    <span class="notif-item-meta">
                        <?= Security::esc($n['actor_name'] ?? '') ?>
                        &middot;
                        <?= Security::esc(date('d.m.Y H:i', strtotime($n['created_at']))) ?>
                    </span>
                </div>
                <?php if ($isUnread): ?>
                <div class="notif-item-actions">
                    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=notification_read" class="inline-form">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                        <button type="submit" class="btn-sm notif-mark-read" title="Als gelesen markieren">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="page-meta" style="margin-top: var(--sp-4);">
    <a href="<?= Security::esc($baseUrl) ?>/?r=settings_notifications" class="text-link">Benachrichtigungseinstellungen</a>
</div>
