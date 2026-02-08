<?php
/**
 * SearchService - unified search across Pages and Tasks.
 *
 * Supports two modes:
 *   - 'like'     : LIKE-based search (always available)
 *   - 'fulltext' : FULLTEXT MATCH … AGAINST (requires FULLTEXT indexes)
 *   - 'auto'     : tries FULLTEXT, falls back to LIKE on error
 *
 * Config key: SEARCH_MODE (default 'like')
 */
class SearchService
{
    private const SNIPPET_LENGTH = 160;

    /**
     * Resolve effective search mode from config value.
     *
     * @return string 'fulltext' or 'like'
     */
    private static function resolveMode(string $configured): string
    {
        if ($configured === 'fulltext') {
            return 'fulltext';
        }

        if ($configured === 'auto') {
            return self::fulltextAvailable() ? 'fulltext' : 'like';
        }

        return 'like';
    }

    /**
     * Check whether FULLTEXT indexes exist on pages and tasks tables.
     */
    private static function fulltextAvailable(): bool
    {
        try {
            $rows = DB::fetchAll(
                "SHOW INDEX FROM pages WHERE Index_type = 'FULLTEXT' AND Key_name = 'ft_pages'"
            );
            if (empty($rows)) {
                return false;
            }

            $rows = DB::fetchAll(
                "SHOW INDEX FROM tasks WHERE Index_type = 'FULLTEXT' AND Key_name = 'ft_tasks'"
            );
            return !empty($rows);
        } catch (Throwable $e) {
            Logger::error('FULLTEXT detection failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ── Page search ─────────────────────────────────────────────────

    /**
     * Search pages by query string.
     *
     * @param string $q     Search query (already trimmed)
     * @param int    $limit Max results
     * @param string $configMode 'auto'|'fulltext'|'like'
     * @return array Array of page result rows
     */
    public static function searchPages(string $q, int $limit = 20, string $configMode = 'like'): array
    {
        $mode = self::resolveMode($configMode);

        if ($mode === 'fulltext') {
            try {
                return self::searchPagesFulltext($q, $limit);
            } catch (Throwable $e) {
                Logger::error('FULLTEXT page search failed, falling back to LIKE', [
                    'error' => $e->getMessage(),
                ]);
                return self::searchPagesLike($q, $limit);
            }
        }

        return self::searchPagesLike($q, $limit);
    }

    private static function searchPagesFulltext(string $q, int $limit): array
    {
        $sql = "SELECT p.id, p.title, p.slug, p.content_md, p.parent_id,
                       p.created_at, p.updated_at,
                       par.title AS parent_title,
                       MATCH(p.title, p.content_md) AGAINST(? IN BOOLEAN MODE) AS relevance,
                       CASE WHEN p.title LIKE ? THEN 10 ELSE 0 END AS title_boost
                FROM pages p
                LEFT JOIN pages par ON par.id = p.parent_id AND par.deleted_at IS NULL
                WHERE p.deleted_at IS NULL
                  AND MATCH(p.title, p.content_md) AGAINST(? IN BOOLEAN MODE)
                ORDER BY title_boost DESC, relevance DESC, p.updated_at DESC
                LIMIT ?";

        $likeTerm = '%' . $q . '%';
        return DB::fetchAll($sql, [$q, $likeTerm, $q, $limit]);
    }

    private static function searchPagesLike(string $q, int $limit): array
    {
        $likeTerm = '%' . $q . '%';

        $sql = "SELECT p.id, p.title, p.slug, p.content_md, p.parent_id,
                       p.created_at, p.updated_at,
                       par.title AS parent_title,
                       CASE WHEN p.title LIKE ? THEN 10 ELSE 0 END AS title_boost
                FROM pages p
                LEFT JOIN pages par ON par.id = p.parent_id AND par.deleted_at IS NULL
                WHERE p.deleted_at IS NULL
                  AND (p.title LIKE ? OR p.content_md LIKE ?)
                ORDER BY title_boost DESC, p.updated_at DESC
                LIMIT ?";

        return DB::fetchAll($sql, [$likeTerm, $likeTerm, $likeTerm, $limit]);
    }

    // ── Task search ─────────────────────────────────────────────────

    /**
     * Search tasks by query string.
     * Results include owner_name and tag_list (comma-separated).
     *
     * @param string $q     Search query (already trimmed)
     * @param int    $limit Max results
     * @param string $configMode 'auto'|'fulltext'|'like'
     * @return array Array of task result rows
     */
    public static function searchTasks(string $q, int $limit = 20, string $configMode = 'like'): array
    {
        $mode = self::resolveMode($configMode);

        if ($mode === 'fulltext') {
            try {
                return self::searchTasksFulltext($q, $limit);
            } catch (Throwable $e) {
                Logger::error('FULLTEXT task search failed, falling back to LIKE', [
                    'error' => $e->getMessage(),
                ]);
                return self::searchTasksLike($q, $limit);
            }
        }

        return self::searchTasksLike($q, $limit);
    }

    private static function searchTasksFulltext(string $q, int $limit): array
    {
        $likeTerm = '%' . $q . '%';

        $sql = "SELECT t.id, t.title, t.description_md, t.status, t.due_date,
                       t.owner_id, t.created_at, t.updated_at,
                       u.name AS owner_name,
                       GROUP_CONCAT(DISTINCT tg.name ORDER BY tg.name SEPARATOR ',') AS tag_list,
                       MATCH(t.title, t.description_md) AGAINST(? IN BOOLEAN MODE) AS relevance,
                       CASE WHEN t.title LIKE ? THEN 10 ELSE 0 END AS title_boost
                FROM tasks t
                LEFT JOIN users u ON u.id = t.owner_id
                LEFT JOIN task_tags tt ON tt.task_id = t.id
                LEFT JOIN tags tg ON tg.id = tt.tag_id
                WHERE MATCH(t.title, t.description_md) AGAINST(? IN BOOLEAN MODE)
                GROUP BY t.id
                ORDER BY title_boost DESC, relevance DESC, t.updated_at DESC
                LIMIT ?";

        return DB::fetchAll($sql, [$q, $likeTerm, $q, $limit]);
    }

    private static function searchTasksLike(string $q, int $limit): array
    {
        $likeTerm = '%' . $q . '%';

        $sql = "SELECT t.id, t.title, t.description_md, t.status, t.due_date,
                       t.owner_id, t.created_at, t.updated_at,
                       u.name AS owner_name,
                       GROUP_CONCAT(DISTINCT tg.name ORDER BY tg.name SEPARATOR ',') AS tag_list,
                       CASE WHEN t.title LIKE ? THEN 10 ELSE 0 END AS title_boost
                FROM tasks t
                LEFT JOIN users u ON u.id = t.owner_id
                LEFT JOIN task_tags tt ON tt.task_id = t.id
                LEFT JOIN tags tg ON tg.id = tt.tag_id
                WHERE (t.title LIKE ? OR t.description_md LIKE ?)
                GROUP BY t.id
                ORDER BY title_boost DESC, t.updated_at DESC
                LIMIT ?";

        return DB::fetchAll($sql, [$likeTerm, $likeTerm, $likeTerm, $limit]);
    }

    // ── Snippet generation ──────────────────────────────────────────

    /**
     * Generate a short, safe snippet from text with the search term highlighted.
     *
     * @param string|null $text    Source text (Markdown)
     * @param string      $query   Search query
     * @param int         $length  Max snippet length
     * @return string HTML-safe snippet with <mark> highlighting
     */
    public static function snippet(?string $text, string $query, int $length = self::SNIPPET_LENGTH): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Strip Markdown formatting for cleaner snippets
        $plain = self::stripMarkdown($text);

        // Find position of the query (case-insensitive)
        $pos = mb_stripos($plain, $query, 0, 'UTF-8');

        if ($pos !== false) {
            // Center the snippet around the match
            $start = max(0, $pos - (int) floor($length / 3));
            $snippet = mb_substr($plain, $start, $length, 'UTF-8');

            if ($start > 0) {
                $snippet = '...' . $snippet;
            }
            if ($start + $length < mb_strlen($plain, 'UTF-8')) {
                $snippet .= '...';
            }
        } else {
            // Query not found in text, take the beginning
            $snippet = mb_substr($plain, 0, $length, 'UTF-8');
            if (mb_strlen($plain, 'UTF-8') > $length) {
                $snippet .= '...';
            }
        }

        // Escape the snippet first, then highlight
        $escaped = htmlspecialchars($snippet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Highlight the query within the escaped snippet
        $escapedQuery = htmlspecialchars($query, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($escapedQuery !== '') {
            $escaped = preg_replace(
                '/(' . preg_quote($escapedQuery, '/') . ')/iu',
                '<mark>$1</mark>',
                $escaped
            );
        }

        return $escaped;
    }

    /**
     * Strip common Markdown formatting to produce plain text.
     */
    private static function stripMarkdown(string $md): string
    {
        // Remove headings markers
        $text = preg_replace('/^#{1,6}\s+/m', '', $md);
        // Remove bold/italic markers
        $text = preg_replace('/\*{1,3}(.+?)\*{1,3}/', '$1', $text);
        // Remove links: [text](url) -> text
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        // Remove inline code backticks
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        // Remove blockquote markers
        $text = preg_replace('/^>\s?/m', '', $text);
        // Remove horizontal rules
        $text = preg_replace('/^-{3,}$/m', '', $text);
        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
