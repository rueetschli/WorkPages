<?php
/**
 * Home view - Dashboard / landing page.
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <h1>Welcome to Work Pages</h1>
    <p class="subtitle">Your workspace for pages, tasks, and decisions -- all in one place.</p>
</div>

<!-- Quick-access cards -->
<div class="card-grid">

    <a href="<?= Security::esc($baseUrl) ?>/?r=pages" class="card">
        <div class="card-icon">&#9783;</div>
        <h3 class="card-title">Pages</h3>
        <p class="card-desc">Create and browse knowledge pages with embedded tasks.</p>
    </a>

    <a href="<?= Security::esc($baseUrl) ?>/?r=board" class="card">
        <div class="card-icon">&#9638;</div>
        <h3 class="card-title">Board</h3>
        <p class="card-desc">Kanban board for visual task management.</p>
    </a>

    <a href="<?= Security::esc($baseUrl) ?>/?r=search" class="card">
        <div class="card-icon">&#8981;</div>
        <h3 class="card-title">Search</h3>
        <p class="card-desc">Find pages and tasks quickly.</p>
    </a>

</div>

<!-- Placeholder sections for future APs -->
<section class="section-block">
    <h2>My Tasks</h2>
    <p class="placeholder-text">Task overview will be available after AP4.</p>
</section>

<section class="section-block">
    <h2>Recent Activity</h2>
    <p class="placeholder-text">Activity feed will be available after AP8.</p>
</section>
