<?php
/**
 * Shared filter block for all report pages (AP18).
 *
 * Expected variables:
 *   $filters    - current filter values (from ReportingService::parseFilters)
 *   $filterData - dropdown data (teams, users, tags, columns)
 *   $baseUrl    - base URL
 *   $reportRoute - current report route name (e.g. 'reports_overview')
 */
$f = $filters;
$fd = $filterData;
$preset = $f['preset'] ?? '30d';
?>
<div class="report-filters">
    <form method="get" action="<?= Security::esc($baseUrl) ?>/" class="report-filter-form">
        <input type="hidden" name="r" value="<?= Security::esc($reportRoute) ?>">

        <!-- Team -->
        <div class="report-filter-group">
            <label class="form-label form-label-sm" for="filter-team">Team</label>
            <select name="team_id" id="filter-team" class="form-input form-input-sm">
                <option value="">Alle sichtbaren</option>
                <?php foreach ($fd['teams'] as $team): ?>
                <option value="<?= (int) $team['id'] ?>"<?= (int) ($f['team_id'] ?? 0) === (int) $team['id'] ? ' selected' : '' ?>><?= Security::esc($team['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Preset -->
        <div class="report-filter-group">
            <label class="form-label form-label-sm" for="filter-preset">Zeitraum</label>
            <select name="preset" id="filter-preset" class="form-input form-input-sm" onchange="toggleCustomDates(this)">
                <option value="7d"<?= $preset === '7d' ? ' selected' : '' ?>>Letzte 7 Tage</option>
                <option value="30d"<?= $preset === '30d' ? ' selected' : '' ?>>Letzte 30 Tage</option>
                <option value="90d"<?= $preset === '90d' ? ' selected' : '' ?>>Letzte 90 Tage</option>
                <option value="quarter"<?= $preset === 'quarter' ? ' selected' : '' ?>>Dieses Quartal</option>
                <option value="custom"<?= $preset === 'custom' ? ' selected' : '' ?>>Benutzerdefiniert</option>
            </select>
        </div>

        <!-- Custom date range -->
        <div class="report-filter-group report-filter-dates" id="custom-dates" style="<?= $preset === 'custom' ? '' : 'display:none' ?>">
            <label class="form-label form-label-sm" for="filter-from">Von</label>
            <input type="date" name="from" id="filter-from" class="form-input form-input-sm"
                   value="<?= Security::esc($f['from'] ?? '') ?>">
            <label class="form-label form-label-sm" for="filter-to">Bis</label>
            <input type="date" name="to" id="filter-to" class="form-input form-input-sm"
                   value="<?= Security::esc($f['to'] ?? '') ?>">
        </div>

        <!-- Owner -->
        <div class="report-filter-group">
            <label class="form-label form-label-sm" for="filter-owner">Owner</label>
            <select name="owner_id" id="filter-owner" class="form-input form-input-sm">
                <option value="">Alle</option>
                <?php foreach ($fd['users'] as $user): ?>
                <option value="<?= (int) $user['id'] ?>"<?= (int) ($f['owner_id'] ?? 0) === (int) $user['id'] ? ' selected' : '' ?>><?= Security::esc($user['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Tag -->
        <div class="report-filter-group">
            <label class="form-label form-label-sm" for="filter-tag">Tag</label>
            <select name="tag" id="filter-tag" class="form-input form-input-sm">
                <option value="">Alle</option>
                <?php foreach ($fd['tags'] as $tag): ?>
                <option value="<?= Security::esc($tag['name']) ?>"<?= ($f['tag'] ?? '') === $tag['name'] ? ' selected' : '' ?>><?= Security::esc($tag['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Column (optional, for aging) -->
        <?php if (!empty($showColumnFilter)): ?>
        <div class="report-filter-group">
            <label class="form-label form-label-sm" for="filter-column">Spalte</label>
            <select name="column_id" id="filter-column" class="form-input form-input-sm">
                <option value="">Alle</option>
                <?php foreach ($fd['columns'] as $col): ?>
                <option value="<?= (int) $col['id'] ?>"<?= (int) ($f['column_id'] ?? 0) === (int) $col['id'] ? ' selected' : '' ?>><?= Security::esc($col['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="report-filter-group report-filter-actions">
            <button type="submit" class="btn btn-primary btn-sm-pad">Filtern</button>
            <a href="<?= Security::esc($baseUrl) ?>/?r=<?= Security::esc($reportRoute) ?>" class="btn btn-secondary btn-sm-pad">Zuruecksetzen</a>
        </div>
    </form>
</div>

<script>
function toggleCustomDates(sel) {
    var el = document.getElementById('custom-dates');
    if (el) {
        el.style.display = sel.value === 'custom' ? '' : 'none';
    }
}
</script>
