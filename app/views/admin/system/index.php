<?php
/**
 * Admin: System info view.
 * Variables: $systemInfo (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1>System-Informationen</h1>
            <p class="subtitle">Uebersicht ueber die Systemumgebung</p>
        </div>
        <div>
            <a href="<?= Security::esc($baseUrl) ?>/?r=admin_users" class="btn btn-secondary">Zurueck zur Verwaltung</a>
        </div>
    </div>
</div>

<div class="section-block">
    <h2>Applikation</h2>
    <table class="pages-table" style="margin-top: 0.75rem;">
        <tbody>
            <tr>
                <td style="font-weight: 600; width: 40%;">App-Version</td>
                <td><?= Security::esc($systemInfo['app_version'] ?? 'Unbekannt') ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">Schema-Version</td>
                <td><?= Security::esc($systemInfo['schema_version'] ?? 'Unbekannt') ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">Installiert am</td>
                <td><?= Security::esc($systemInfo['installed_at'] ?? 'Unbekannt') ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="section-block">
    <h2>Server</h2>
    <table class="pages-table" style="margin-top: 0.75rem;">
        <tbody>
            <tr>
                <td style="font-weight: 600; width: 40%;">PHP-Version</td>
                <td><?= Security::esc($systemInfo['php_version']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">MySQL-Version</td>
                <td><?= Security::esc($systemInfo['mysql_version'] ?? 'Unbekannt') ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">memory_limit</td>
                <td><?= Security::esc($systemInfo['memory_limit']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">upload_max_filesize</td>
                <td><?= Security::esc($systemInfo['upload_max_filesize']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">post_max_size</td>
                <td><?= Security::esc($systemInfo['post_max_size']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">max_execution_time</td>
                <td><?= Security::esc($systemInfo['max_execution_time']) ?> s</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="section-block">
    <h2>Schreibrechte</h2>
    <table class="pages-table" style="margin-top: 0.75rem;">
        <tbody>
            <?php foreach ($systemInfo['writable_checks'] as $path => $writable): ?>
            <tr>
                <td style="font-weight: 600; width: 40%;"><?= Security::esc($path) ?></td>
                <td>
                    <?php if ($writable): ?>
                        <span class="status-badge status-doing">Beschreibbar</span>
                    <?php else: ?>
                        <span class="status-badge status-backlog">Nicht beschreibbar</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="section-block">
    <h2>PHP-Extensions</h2>
    <table class="pages-table" style="margin-top: 0.75rem;">
        <tbody>
            <?php foreach ($systemInfo['extensions'] as $ext => $loaded): ?>
            <tr>
                <td style="font-weight: 600; width: 40%;"><?= Security::esc($ext) ?></td>
                <td>
                    <?php if ($loaded): ?>
                        <span class="status-badge status-doing">Geladen</span>
                    <?php else: ?>
                        <span class="status-badge status-backlog">Fehlt</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="section-block">
    <h2>Konfiguration</h2>
    <table class="pages-table" style="margin-top: 0.75rem;">
        <tbody>
            <tr>
                <td style="font-weight: 600; width: 40%;">SEARCH_MODE</td>
                <td><code><?= Security::esc($systemInfo['search_mode']) ?></code></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">DEBUG</td>
                <td><code><?= $systemInfo['debug'] ? 'true' : 'false' ?></code></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">INSTALL_UNLOCK</td>
                <td><code><?= $systemInfo['install_unlock'] ? 'true' : 'false' ?></code></td>
            </tr>
        </tbody>
    </table>
</div>
