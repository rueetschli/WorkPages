<?php
/**
 * Admin health dashboard view (AP28).
 *
 * Variables:
 *   $checks        - array from HealthCheckService::runAll()
 *   $overallStatus - 'ok' | 'warning' | 'error'
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');

/**
 * Render an inline status dot (Ampel).
 */
function healthDot(string $status): string
{
    $colors = [
        'ok'      => '#22c55e',
        'warning' => '#f59e0b',
        'error'   => '#ef4444',
    ];
    $color = $colors[$status] ?? '#94a3b8';
    return '<span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:' . $color . ';flex-shrink:0;" aria-hidden="true"></span>';
}

/**
 * Render a status badge label.
 */
function healthBadge(string $status): string
{
    $map = [
        'ok'      => ['color' => '#15803d', 'bg' => '#dcfce7', 'key' => 'health.status.ok'],
        'warning' => ['color' => '#92400e', 'bg' => '#fef3c7', 'key' => 'health.status.warning'],
        'error'   => ['color' => '#991b1b', 'bg' => '#fee2e2', 'key' => 'health.status.error'],
    ];
    $cfg   = $map[$status] ?? $map['ok'];
    $label = Security::esc(t($cfg['key']));
    return '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:0.78rem;font-weight:600;'
         . 'color:' . $cfg['color'] . ';background:' . $cfg['bg'] . ';">'
         . $label . '</span>';
}

$sectionKeys = [
    'system'     => 'health.section.system',
    'filesystem' => 'health.section.filesystem',
    'database'   => 'health.section.database',
    'email'      => 'health.section.email',
    'webhooks'   => 'health.section.webhooks',
    'config'     => 'health.section.config',
];
?>

<style>
.health-sections { display: flex; flex-direction: column; gap: var(--sp-3, 0.75rem); }
.health-card { border: 1px solid var(--border, #e2e8f0); border-radius: 8px; overflow: hidden; }
.health-card summary {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.85rem 1rem; cursor: pointer;
    font-weight: 600; font-size: 0.95rem;
    list-style: none; user-select: none;
    background: var(--bg-card, #fff);
}
.health-card summary::-webkit-details-marker { display: none; }
.health-card summary:hover { background: var(--bg-hover, #f8fafc); }
.health-card-body { padding: 0 1rem 1rem; background: var(--bg-card, #fff); }
.health-card[open] summary { border-bottom: 1px solid var(--border, #e2e8f0); }
.health-summary-title { flex: 1; }
.health-caret { margin-left: auto; font-size: 0.8rem; color: var(--text-muted, #64748b); transition: transform 0.15s; }
.health-card[open] .health-caret { transform: rotate(180deg); }
.health-check-table { width: 100%; border-collapse: collapse; margin-top: 0.75rem; font-size: 0.88rem; }
.health-check-table th,
.health-check-table td { padding: 0.45rem 0.5rem; text-align: left; vertical-align: middle; }
.health-check-table th { color: var(--text-muted, #64748b); font-weight: 600; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em; border-bottom: 1px solid var(--border, #e2e8f0); }
.health-check-table tr + tr td { border-top: 1px solid var(--border-light, #f1f5f9); }
.health-check-table td:first-child { color: var(--text-muted, #64748b); white-space: nowrap; }
.health-check-table td:nth-child(2) { font-weight: 500; }
.health-hint { font-size: 0.8rem; color: var(--text-muted, #64748b); margin-top: 0.15rem; }
.health-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--border-light, #f1f5f9); }
.health-overall { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 600; margin-bottom: var(--sp-4, 1rem); }
.health-overall-ok      { background: #dcfce7; color: #15803d; }
.health-overall-warning { background: #fef3c7; color: #92400e; }
.health-overall-error   { background: #fee2e2; color: #991b1b; }
</style>

<div class="page-header">
    <div class="page-header-row">
        <h1><?= Security::esc(t('health.page_title')) ?></h1>
    </div>
    <p class="text-muted"><?= Security::esc(t('health.page_subtitle')) ?></p>
</div>

<!-- Overall status banner -->
<div class="health-overall health-overall-<?= Security::esc($overallStatus) ?>">
    <?= healthDot($overallStatus) ?>
    <?php if ($overallStatus === 'ok'): ?>
        <?= Security::esc(t('health.overall_ok')) ?>
    <?php elseif ($overallStatus === 'warning'): ?>
        <?= Security::esc(t('health.overall_warning')) ?>
    <?php else: ?>
        <?= Security::esc(t('health.overall_error')) ?>
    <?php endif; ?>
</div>

<div class="health-sections">

<?php foreach ($sectionKeys as $sectionKey => $titleKey): ?>
<?php
    $section = $checks[$sectionKey] ?? ['status' => 'ok', 'items' => []];
    $sStatus = $section['status'];
    $sItems  = $section['items'];
    // Open erroneous or warning sections by default; collapse OK sections
    $openAttr = ($sStatus !== 'ok') ? ' open' : '';
?>

<details class="health-card"<?= $openAttr ?>>
    <summary>
        <?= healthDot($sStatus) ?>
        <span class="health-summary-title"><?= Security::esc(t($titleKey)) ?></span>
        <?= healthBadge($sStatus) ?>
        <span class="health-caret">&#9660;</span>
    </summary>

    <div class="health-card-body">
        <?php if (!empty($sItems)): ?>
        <table class="health-check-table">
            <thead>
                <tr>
                    <th><?= Security::esc(t('health.th_check')) ?></th>
                    <th><?= Security::esc(t('health.th_value')) ?></th>
                    <th><?= Security::esc(t('health.th_status')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sItems as $item): ?>
                <tr>
                    <td>
                        <?php if (!empty($item['label_raw'])): ?>
                            <code><?= Security::esc($item['label']) ?></code>
                        <?php else: ?>
                            <?= Security::esc(t($item['label'])) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= Security::esc((string) $item['value']) ?>
                        <?php if (!empty($item['hint'])): ?>
                            <div class="health-hint"><?= Security::esc(t($item['hint'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= healthDot($item['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if ($sectionKey === 'email'): ?>
        <div class="health-actions">
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_health_mail_test" class="inline-form">
                <?= Security::csrfField() ?>
                <button type="submit" class="btn btn-secondary btn-sm-pad">
                    <?= Security::esc(t('health.email.action_mail_test')) ?>
                </button>
            </form>
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_health_mail_send" class="inline-form">
                <?= Security::csrfField() ?>
                <button type="submit" class="btn btn-secondary btn-sm-pad">
                    <?= Security::esc(t('health.email.action_mail_send')) ?>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($sectionKey === 'webhooks'): ?>
        <div class="health-actions">
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_health_webhook_send" class="inline-form">
                <?= Security::csrfField() ?>
                <button type="submit" class="btn btn-secondary btn-sm-pad">
                    <?= Security::esc(t('health.webhooks.action_send')) ?>
                </button>
            </form>
        </div>
        <?php endif; ?>

    </div>
</details>

<?php endforeach; ?>

</div>
