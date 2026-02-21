<?php
/**
 * Search results view.
 *
 * Variables:
 *   $query       - string, the search query
 *   $typeFilter  - string, 'all'|'pages'|'tasks'
 *   $error       - string|null, error message
 *   $pageResults - array, page search results
 *   $taskResults - array, task search results
 *   $totalResults - int, total number of results
 *   $searched    - bool, whether a search was performed
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <h1><?= Security::esc(t('search.title')) ?></h1>
</div>

<!-- Search form -->
<div class="search-page-form">
    <form action="<?= Security::esc($baseUrl) ?>/" method="get" class="filter-form">
        <input type="hidden" name="r" value="search">
        <div class="filter-row">
            <div class="filter-group" style="flex: 1;">
                <label for="search-q"><?= Security::esc(t('labels.search_term')) ?></label>
                <input type="text"
                       id="search-q"
                       name="q"
                       value="<?= Security::esc($query) ?>"
                       placeholder="<?= Security::esc(t('placeholders.search')) ?>"
                       class="form-input form-input-sm"
                       autofocus>
            </div>
            <div class="filter-group" style="min-width: 160px;">
                <label for="search-type"><?= Security::esc(t('labels.type')) ?></label>
                <select id="search-type" name="type" class="form-input form-input-sm">
                    <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>><?= Security::esc(t('search.type_all')) ?></option>
                    <option value="pages" <?= $typeFilter === 'pages' ? 'selected' : '' ?>><?= Security::esc(t('search.type_pages')) ?></option>
                    <option value="tasks" <?= $typeFilter === 'tasks' ? 'selected' : '' ?>><?= Security::esc(t('search.type_tasks')) ?></option>
                </select>
            </div>
            <div class="filter-group filter-actions">
                <button type="submit" class="btn btn-primary btn-sm-pad"><?= Security::esc(t('actions.search')) ?></button>
                <?php if ($query !== ''): ?>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=search" class="btn btn-secondary btn-sm-pad"><?= Security::esc(t('actions.reset')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if ($error !== null): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<?php if ($query === '' && !$searched): ?>
    <div class="section-block">
        <p class="placeholder-text"><?= Security::esc(t('messages.search_hint')) ?></p>
    </div>
<?php elseif ($searched && $totalResults === 0): ?>
    <div class="section-block">
        <p class="placeholder-text"><?= Security::esc(t('messages.no_results_for', ['query' => $query])) ?></p>
    </div>
<?php elseif ($searched): ?>

    <p class="search-result-summary">
        <?= $totalResults !== 1 ? Security::esc(t('messages.results_for_plural', ['count' => $totalResults, 'query' => $query])) : Security::esc(t('messages.results_for', ['count' => $totalResults, 'query' => $query])) ?>
    </p>

    <?php if (!empty($pageResults)): ?>
    <div class="search-section">
        <h2 class="search-section-title">Pages <span class="search-section-count"><?= count($pageResults) ?></span></h2>
        <div class="search-results">
            <?php foreach ($pageResults as $page): ?>
            <div class="search-result-card">
                <div class="search-result-header">
                    <span class="search-type-badge search-type-page">Page</span>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&amp;slug=<?= Security::esc($page['slug']) ?>"
                       class="search-result-title"><?= Security::esc($page['title']) ?></a>
                </div>
                <?php if (!empty($page['parent_title'])): ?>
                    <div class="search-result-breadcrumb">
                        <?= Security::esc($page['parent_title']) ?> / <?= Security::esc($page['title']) ?>
                    </div>
                <?php endif; ?>
                <div class="search-result-snippet">
                    <?= SearchService::snippet($page['content_md'], $query) ?>
                </div>
                <div class="search-result-meta">
                    <?php if (!empty($page['updated_at'])): ?>
                        <span><?= Security::esc(t('search.updated', ['date' => date('d.m.Y', strtotime($page['updated_at']))])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($taskResults)): ?>
    <div class="search-section">
        <h2 class="search-section-title">Tasks <span class="search-section-count"><?= count($taskResults) ?></span></h2>
        <div class="search-results">
            <?php foreach ($taskResults as $task): ?>
            <div class="search-result-card">
                <div class="search-result-header">
                    <span class="search-type-badge search-type-task">Task</span>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&amp;id=<?= (int) $task['id'] ?>"
                       class="search-result-title"><?= Security::esc($task['title']) ?></a>
                    <span class="status-badge">
                        <?= Security::esc($task['column_name'] ?? '') ?>
                    </span>
                </div>
                <div class="search-result-snippet">
                    <?= SearchService::snippet($task['description_md'], $query) ?>
                </div>
                <div class="search-result-meta">
                    <?php if (!empty($task['owner_name'])): ?>
                        <span class="search-meta-item">Owner: <?= Security::esc($task['owner_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($task['due_date'])): ?>
                        <?php
                            $isOverdue = $task['due_date'] < date('Y-m-d') && ($task['column_slug'] ?? '') !== 'done';
                        ?>
                        <span class="search-meta-item <?= $isOverdue ? 'text-overdue' : '' ?>">
                            <?= Security::esc(t('search.due', ['date' => date('d.m.Y', strtotime($task['due_date']))])) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($task['tag_list'])): ?>
                        <span class="search-meta-tags">
                            <?php foreach (explode(',', $task['tag_list']) as $tagName): ?>
                                <span class="tag-chip"><?= Security::esc(trim($tagName)) ?></span>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>
