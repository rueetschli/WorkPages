<?php
/**
 * SearchController - unified search across Pages and Tasks.
 */
class SearchController
{
    /** Minimum query length (characters). */
    private const MIN_QUERY_LENGTH = 2;

    /** Maximum results per type. */
    private const RESULTS_LIMIT = 20;

    /**
     * Display search form and results.
     */
    public function index(): void
    {
        $query       = trim($_GET['q'] ?? '');
        $typeFilter  = $_GET['type'] ?? 'all';
        $error       = null;
        $pageResults = [];
        $taskResults = [];
        $searched    = false;

        // Validate type filter
        if (!in_array($typeFilter, ['all', 'pages', 'tasks'], true)) {
            $typeFilter = 'all';
        }

        if ($query !== '') {
            if (mb_strlen($query, 'UTF-8') < self::MIN_QUERY_LENGTH) {
                $error = 'Bitte mindestens ' . self::MIN_QUERY_LENGTH . ' Zeichen eingeben.';
            } else {
                $searched   = true;
                $searchMode = $GLOBALS['config']['SEARCH_MODE'] ?? 'like';

                try {
                    if ($typeFilter === 'all' || $typeFilter === 'pages') {
                        $pageResults = SearchService::searchPages($query, self::RESULTS_LIMIT, $searchMode);
                    }

                    if ($typeFilter === 'all' || $typeFilter === 'tasks') {
                        $taskResults = SearchService::searchTasks($query, self::RESULTS_LIMIT, $searchMode);
                    }
                } catch (Throwable $e) {
                    Logger::error('Search failed', [
                        'query' => $query,
                        'error' => $e->getMessage(),
                    ]);
                    $error = 'Bei der Suche ist ein Fehler aufgetreten. Bitte versuche es erneut.';
                }
            }
        }

        $totalResults = count($pageResults) + count($taskResults);

        $pageTitle   = 'Suche';
        $contentView = APP_DIR . '/views/search/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Redirect helper.
     */
    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
