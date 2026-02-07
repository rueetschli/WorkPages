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
     * Create a new page. Returns the new page ID.
     */
    public static function create(array $data): int
    {
        $slug = self::generateUniqueSlug($data['title']);

        DB::query(
            'INSERT INTO pages (title, slug, parent_id, content_md, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [
                $data['title'],
                $slug,
                $data['parent_id'] ?: null,
                $data['content_md'] ?? '',
                $data['created_by'],
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

        DB::query(
            'UPDATE pages
             SET title = ?, slug = ?, parent_id = ?, content_md = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ?',
            [
                $data['title'] ?? $page['title'],
                $slug,
                $data['parent_id'] ?: null,
                $data['content_md'] ?? $page['content_md'],
                $data['updated_by'],
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
