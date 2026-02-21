<?php
/**
 * Add task to page - search existing tasks or create a new one.
 * Variables: $page, $searchResults, $searchQuery, $error, $users
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <li class="breadcrumb-item">
            <a href="<?= Security::esc($baseUrl) ?>/?r=pages"><?= Security::esc(t('pages.title')) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&amp;slug=<?= Security::esc($page['slug']) ?>">
                <?= Security::esc($page['title']) ?>
            </a>
        </li>
        <li class="breadcrumb-item breadcrumb-current">
            <span><?= Security::esc(t('pages.add_task_title')) ?></span>
        </li>
    </ol>
</nav>

<div class="page-header">
    <h1><?= Security::esc(t('pages.add_task_title')) ?></h1>
    <p class="subtitle"><?= Security::esc(t('pages.title')) ?>: <?= Security::esc($page['title']) ?></p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<!-- Search existing tasks -->
<div class="section-block">
    <h2><?= Security::esc(t('pages.link_existing')) ?></h2>

    <form method="get" action="<?= Security::esc($baseUrl) ?>/" class="task-search-form">
        <input type="hidden" name="r" value="page_tasks_add">
        <input type="hidden" name="slug" value="<?= Security::esc($page['slug']) ?>">
        <div class="filter-row">
            <div class="filter-group" style="flex:1;">
                <label for="task-search-q"><?= Security::esc(t('pages.search_task')) ?></label>
                <input type="text" id="task-search-q" name="q"
                       value="<?= Security::esc($searchQuery) ?>"
                       placeholder="<?= Security::esc(t('placeholders.title_input')) ?>"
                       class="form-input form-input-sm">
            </div>
            <div class="filter-group filter-actions">
                <button type="submit" class="btn btn-primary btn-sm-pad"><?= Security::esc(t('actions.search')) ?></button>
            </div>
        </div>
    </form>

    <?php if ($searchQuery !== ''): ?>
        <?php if (empty($searchResults)): ?>
            <p class="placeholder-text" style="margin-top:var(--sp-4);"><?= Security::esc(t('messages.no_tasks_found_for', ['query' => $searchQuery])) ?></p>
        <?php else: ?>
            <table class="pages-table" style="margin-top:var(--sp-4);">
                <thead>
                    <tr>
                        <th><?= Security::esc(t('labels.title')) ?></th>
                        <th><?= Security::esc(t('labels.column')) ?></th>
                        <th><?= Security::esc(t('labels.owner')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($searchResults as $result): ?>
                    <tr>
                        <td><?= Security::esc($result['title']) ?></td>
                        <td>
                            <span class="status-badge">
                                <?= Security::esc($result['column_name'] ?? '') ?>
                            </span>
                        </td>
                        <td><?= $result['owner_name'] ? Security::esc($result['owner_name']) : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=page_tasks_add&amp;slug=<?= Security::esc($page['slug']) ?>" class="inline-form">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="action" value="link">
                                <input type="hidden" name="task_id" value="<?= (int) $result['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm-pad"><?= Security::esc(t('actions.link')) ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Create new task and link -->
<div class="section-block">
    <h2><?= Security::esc(t('pages.create_and_link')) ?></h2>

    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=page_tasks_add&amp;slug=<?= Security::esc($page['slug']) ?>">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="create">

        <div class="form-group">
            <label for="new-task-title"><?= Security::esc(t('labels.title')) ?> *</label>
            <input type="text" id="new-task-title" name="title" class="form-input" required
                   value="<?= Security::esc($_POST['title'] ?? '') ?>">
        </div>

        <div class="form-row">
            <div class="form-group form-group-half">
                <label for="new-task-owner"><?= Security::esc(t('labels.owner')) ?></label>
                <select id="new-task-owner" name="owner_id" class="form-input">
                    <option value=""><?= Security::esc(t('tasks.owner_none')) ?></option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"
                            <?= ((string)($u['id'] ?? '')) === ($_POST['owner_id'] ?? '') ? 'selected' : '' ?>>
                            <?= Security::esc($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group form-group-half">
                <label for="new-task-due"><?= Security::esc(t('labels.due_date')) ?></label>
                <input type="date" id="new-task-due" name="due_date" class="form-input"
                       value="<?= Security::esc($_POST['due_date'] ?? '') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= Security::esc(t('actions.create_and_link')) ?></button>
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&amp;slug=<?= Security::esc($page['slug']) ?>" class="btn btn-secondary"><?= Security::esc(t('actions.cancel')) ?></a>
        </div>
    </form>
</div>
