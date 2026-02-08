<?php
/**
 * Activity log partial - displays a list of activity entries.
 *
 * Variables expected:
 *   $activities - array of activity rows (from ActivityService::listFor)
 */
?>
<div class="section-block activity-section">
    <h2>Aktivitaet</h2>

    <?php if (empty($activities)): ?>
        <p class="placeholder-text">Keine Aktivitaet vorhanden.</p>
    <?php else: ?>
        <ul class="activity-list">
            <?php foreach ($activities as $entry): ?>
            <li class="activity-item">
                <span class="activity-time"><?= Security::esc(date('d.m.Y H:i', strtotime($entry['created_at']))) ?></span>
                <span class="activity-text"><?= ActivityService::formatActivity($entry) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
