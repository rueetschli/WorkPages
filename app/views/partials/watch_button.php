<?php
/**
 * Watch button partial (AP15).
 * Variables required: $watchEntityType (string), $watchEntityId (int)
 */
if (!isset($watchEntityType, $watchEntityId)) {
    return;
}

$__canWatch = Authz::can(Authz::WATCH_TOGGLE);
if (!$__canWatch) {
    return;
}

$__userId     = (int) ($_SESSION['user_id'] ?? 0);
$__isWatching = $__userId > 0 ? Watcher::isWatching($watchEntityType, $watchEntityId, $__userId) : false;
$__watchCount = Watcher::countWatchers($watchEntityType, $watchEntityId);
$__baseUrl    = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<form method="post" action="<?= Security::esc($__baseUrl) ?>/?r=watch_toggle" class="inline-form watch-form">
    <?= Security::csrfField() ?>
    <input type="hidden" name="entity_type" value="<?= Security::esc($watchEntityType) ?>">
    <input type="hidden" name="entity_id" value="<?= (int) $watchEntityId ?>">
    <input type="hidden" name="state" value="<?= $__isWatching ? 'off' : 'on' ?>">
    <button type="submit" class="btn btn-secondary btn-sm-pad watch-btn btn-responsive <?= $__isWatching ? 'watch-active' : '' ?>"
            title="<?= $__isWatching ? Security::esc(t('watch.unwatch')) : Security::esc(t('watch.watch')) ?>">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        <span class="btn-label"><?= $__isWatching ? Security::esc(t('watch.watching')) : Security::esc(t('watch.watch')) ?></span>
        <?php if ($__watchCount > 0): ?>
            <span class="watch-count"><?= (int) $__watchCount ?></span>
        <?php endif; ?>
    </button>
</form>
