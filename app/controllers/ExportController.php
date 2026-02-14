<?php
/**
 * ExportController - CSV and Markdown exports (AP10).
 */
class ExportController
{
    /**
     * Export all tasks as CSV.
     * Access: member, admin
     */
    public function tasksCsv(): void
    {
        Authz::requireRole(['admin', 'member']);

        $tasks = DB::fetchAll(
            "SELECT t.id, t.title,
                    bc.name AS column_name,
                    u.name AS owner_name,
                    t.due_date,
                    t.created_at,
                    t.updated_at
             FROM tasks t
             LEFT JOIN users u ON u.id = t.owner_id
             LEFT JOIN board_columns bc ON bc.id = t.column_id
             ORDER BY t.created_at DESC"
        );

        // Collect tags for all tasks
        $allTags = [];
        if (!empty($tasks)) {
            $taskIds = array_column($tasks, 'id');
            $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
            $tagRows = DB::fetchAll(
                "SELECT tt.task_id, tg.name
                 FROM task_tags tt
                 JOIN tags tg ON tg.id = tt.tag_id
                 WHERE tt.task_id IN ({$placeholders})
                 ORDER BY tg.name",
                $taskIds
            );
            foreach ($tagRows as $tr) {
                $allTags[(int) $tr['task_id']][] = $tr['name'];
            }
        }

        // HTTP headers for CSV download
        $filename = 'workpages-tasks-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // BOM for Excel UTF-8 compatibility
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');

        // Header row
        fputcsv($out, ['ID', 'Titel', 'Spalte', 'Owner', 'Faelligkeitsdatum', 'Tags', 'Erstellt', 'Aktualisiert'], ';');

        foreach ($tasks as $task) {
            $tags = $allTags[(int) $task['id']] ?? [];
            fputcsv($out, [
                $task['id'],
                $task['title'],
                $task['column_name'] ?? '',
                $task['owner_name'] ?? '',
                $task['due_date'] ?? '',
                implode(', ', $tags),
                $task['created_at'],
                $task['updated_at'] ?? '',
            ], ';');
        }

        fclose($out);
        exit;
    }

    /**
     * Export a single page as Markdown file.
     * Access: all authenticated users (view permission)
     */
    public function pageMd(): void
    {
        Authz::require(Authz::PAGE_VIEW);

        $slug = trim($_GET['slug'] ?? '');
        if ($slug === '') {
            http_response_code(400);
            echo 'Parameter slug fehlt.';
            exit;
        }

        $page = Page::findBySlug($slug);
        if (!$page) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            exit;
        }

        $filename = $page['slug'] . '.md';

        // Build markdown content
        $content = '# ' . $page['title'] . "\n\n";
        $content .= $page['content_md'];

        header('Content-Type: text/markdown; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $content;
        exit;
    }
}
