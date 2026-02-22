<?php
/**
 * Page model - database operations for the pages table.
 */
class Page
{
    /**
     * Find a page by ID (excluding soft-deleted).
     */
    public static function findById(int $id): ?array
    {
        return DB::fetch(
            'SELECT * FROM pages WHERE id = ? AND deleted_at IS NULL',
            [$id]
        );
    }

    /**
     * Find a page by slug (excluding soft-deleted).
     */
    public static function findBySlug(string $slug): ?array
    {
        return DB::fetch(
            'SELECT * FROM pages WHERE slug = ? AND deleted_at IS NULL',
            [$slug]
        );
    }

    /**
     * Fetch all non-deleted pages ordered by title.
     */
    public static function all(): array
    {
        return DB::fetchAll(
            'SELECT p.*, u.name AS creator_name, par.title AS parent_title
             FROM pages p
             LEFT JOIN users u ON u.id = p.created_by
             LEFT JOIN pages par ON par.id = p.parent_id AND par.deleted_at IS NULL
             WHERE p.deleted_at IS NULL
             ORDER BY p.title ASC'
        );
    }

    /**
     * AP16: Fetch all pages visible to a user, filtered by team.
     */
    public static function allVisible(int $userId, string $globalRole, ?int $filterTeamId = null): array
    {
        [$visSql, $visParams] = TeamService::pageVisibilityWhere($userId, $globalRole, 'p', $filterTeamId);

        $sql = "SELECT p.*, u.name AS creator_name, par.title AS parent_title
                FROM pages p
                LEFT JOIN users u ON u.id = p.created_by
                LEFT JOIN pages par ON par.id = p.parent_id AND par.deleted_at IS NULL
                WHERE p.deleted_at IS NULL AND {$visSql}
                ORDER BY p.title ASC";

        return DB::fetchAll($sql, $visParams);
    }

    /**
     * Create a new page. Returns the new page ID.
     */
    public static function create(array $data): int
    {
        $slug = self::generateUniqueSlug($data['title']);
        $teamId = !empty($data['team_id']) ? (int) $data['team_id'] : null;

        DB::query(
            'INSERT INTO pages (title, slug, parent_id, content_md, created_by, team_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $data['title'],
                $slug,
                $data['parent_id'] ?: null,
                $data['content_md'] ?? '',
                $data['created_by'],
                $teamId,
            ]
        );

        return (int) DB::lastInsertId();
    }

    /**
     * Update an existing page by ID.
     */
    public static function update(int $id, array $data): void
    {
        $page = self::findById($id);
        if (!$page) {
            return;
        }

        $slug = $page['slug'];
        if (isset($data['title']) && $data['title'] !== $page['title']) {
            $slug = self::generateUniqueSlug($data['title'], $id);
        }

        $teamId = array_key_exists('team_id', $data)
            ? (!empty($data['team_id']) ? (int) $data['team_id'] : null)
            : ($page['team_id'] ?? null);

        DB::query(
            'UPDATE pages
             SET title = ?, slug = ?, parent_id = ?, content_md = ?, updated_by = ?, team_id = ?, updated_at = NOW()
             WHERE id = ?',
            [
                $data['title'] ?? $page['title'],
                $slug,
                $data['parent_id'] ?: null,
                $data['content_md'] ?? $page['content_md'],
                $data['updated_by'],
                $teamId,
                $id,
            ]
        );
    }

    /**
     * Soft-delete a page by setting deleted_at.
     */
    public static function softDelete(int $id): void
    {
        DB::query(
            'UPDATE pages SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL',
            [$id]
        );
    }

    /**
     * Build a hierarchical tree of all non-deleted pages.
     * Returns a nested array structure.
     */
    public static function getTree(): array
    {
        $pages = DB::fetchAll(
            'SELECT id, title, slug, parent_id
             FROM pages
             WHERE deleted_at IS NULL
             ORDER BY title ASC'
        );

        return self::buildTree($pages, null);
    }

    /**
     * AP16: Build a hierarchical tree filtered by team visibility.
     */
    public static function getTreeVisible(int $userId, string $globalRole, ?int $filterTeamId = null): array
    {
        [$visSql, $visParams] = TeamService::pageVisibilityWhere($userId, $globalRole, 'p', $filterTeamId);

        $pages = DB::fetchAll(
            "SELECT p.id, p.title, p.slug, p.parent_id
             FROM pages p
             WHERE p.deleted_at IS NULL AND {$visSql}
             ORDER BY p.title ASC",
            $visParams
        );

        return self::buildTree($pages, null);
    }

    /**
     * Recursively build tree from flat list.
     */
    private static function buildTree(array $pages, ?int $parentId): array
    {
        $branch = [];
        foreach ($pages as $page) {
            $pid = $page['parent_id'] !== null ? (int) $page['parent_id'] : null;
            if ($pid === $parentId) {
                $page['children'] = self::buildTree($pages, (int) $page['id']);
                $branch[] = $page;
            }
        }
        return $branch;
    }

    /**
     * Get breadcrumb path from root to the given page.
     * Returns array of ['id', 'title', 'slug'] from root to current page.
     */
    public static function getBreadcrumb(int $pageId): array
    {
        $crumbs = [];
        $visited = [];
        $currentId = $pageId;

        while ($currentId !== null) {
            if (in_array($currentId, $visited, true)) {
                break;
            }
            $visited[] = $currentId;

            $page = DB::fetch(
                'SELECT id, title, slug, parent_id FROM pages WHERE id = ? AND deleted_at IS NULL',
                [$currentId]
            );

            if (!$page) {
                break;
            }

            array_unshift($crumbs, $page);
            $currentId = $page['parent_id'] !== null ? (int) $page['parent_id'] : null;
        }

        return $crumbs;
    }

    /**
     * Get all non-deleted pages as flat list for dropdowns.
     */
    public static function allForDropdown(?int $excludeId = null): array
    {
        if ($excludeId !== null) {
            return DB::fetchAll(
                'SELECT id, title, parent_id FROM pages WHERE deleted_at IS NULL AND id != ? ORDER BY title ASC',
                [$excludeId]
            );
        }
        return DB::fetchAll(
            'SELECT id, title, parent_id FROM pages WHERE deleted_at IS NULL ORDER BY title ASC'
        );
    }

    // -- AP30: Move & Copy -----------------------------------------------

    /**
     * AP30: Check if moving $pageId under $newParentId would create a cycle.
     * A cycle exists if $newParentId is the page itself or any descendant of $pageId.
     */
    public static function wouldCreateCycle(int $pageId, int $newParentId): bool
    {
        if ($pageId === $newParentId) {
            return true;
        }

        // Walk upward from $newParentId; if we reach $pageId, it's a cycle
        $visited = [];
        $currentId = $newParentId;

        while ($currentId !== null) {
            if ($currentId === $pageId) {
                return true;
            }
            if (in_array($currentId, $visited, true)) {
                break; // already a broken cycle in DB, stop
            }
            $visited[] = $currentId;

            $row = DB::fetch(
                'SELECT parent_id FROM pages WHERE id = ? AND deleted_at IS NULL',
                [$currentId]
            );
            if (!$row) {
                break;
            }
            $currentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        }

        return false;
    }

    /**
     * AP30: Move a page to a new parent (or to root if $newParentId is null).
     */
    public static function moveTo(int $pageId, ?int $newParentId, int $updatedBy): void
    {
        DB::query(
            'UPDATE pages SET parent_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL',
            [$newParentId, $updatedBy, $pageId]
        );
    }

    /**
     * AP30: Get all non-deleted pages visible to user for parent selection.
     * Excludes the given page and all its descendants.
     */
    public static function allForMoveTarget(int $excludePageId, int $userId, string $globalRole, ?int $filterTeamId = null): array
    {
        // Get all descendants of excludePageId to exclude them
        $excludeIds = self::getDescendantIds($excludePageId);
        $excludeIds[] = $excludePageId;

        [$visSql, $visParams] = TeamService::pageVisibilityWhere($userId, $globalRole, 'p', $filterTeamId);

        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));

        $sql = "SELECT p.id, p.title, p.parent_id, p.team_id
                FROM pages p
                WHERE p.deleted_at IS NULL AND p.id NOT IN ({$placeholders}) AND {$visSql}
                ORDER BY p.title ASC";

        $params = array_merge($excludeIds, $visParams);
        return DB::fetchAll($sql, $params);
    }

    /**
     * AP30: Get all descendant IDs of a page (recursive).
     */
    public static function getDescendantIds(int $pageId): array
    {
        $descendants = [];
        $children = DB::fetchAll(
            'SELECT id FROM pages WHERE parent_id = ? AND deleted_at IS NULL',
            [$pageId]
        );

        foreach ($children as $child) {
            $childId = (int) $child['id'];
            $descendants[] = $childId;
            $descendants = array_merge($descendants, self::getDescendantIds($childId));
        }

        return $descendants;
    }

    /**
     * Generate a URL-safe slug from a title.
     */
    public static function slugify(string $title): string
    {
        $slug = mb_strtolower($title, 'UTF-8');

        $translitMap = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];
        $slug = strtr($slug, $translitMap);

        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'page';
        }

        return $slug;
    }

    /**
     * Generate a unique slug, appending numeric suffix on conflict.
     */
    public static function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = self::slugify($title);
        $slug = $base;
        $counter = 1;

        while (true) {
            if ($excludeId !== null) {
                $existing = DB::fetch(
                    'SELECT id FROM pages WHERE slug = ? AND id != ?',
                    [$slug, $excludeId]
                );
            } else {
                $existing = DB::fetch(
                    'SELECT id FROM pages WHERE slug = ?',
                    [$slug]
                );
            }

            if (!$existing) {
                return $slug;
            }

            $counter++;
            $slug = $base . '-' . $counter;
        }
    }
}
