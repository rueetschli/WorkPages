<?php
/**
 * TemplateService - Template scanner, import, and idempotency logic (AP31).
 *
 * Scans /templates/ directory for Markdown files, imports them as Pages
 * with proper hierarchy, and tracks imports for idempotency.
 */
class TemplateService
{
    /**
     * Scan the /templates/ directory and return all available templates.
     *
     * Returns array of:
     *   [
     *     'key'      => 'scrum/daily-de',
     *     'category' => 'scrum',
     *     'language' => 'de',
     *     'filename' => 'daily-de.md',
     *     'title'    => 'Daily Standup',     // from H1 or filename
     *     'path'     => '/absolute/path/to/daily-de.md',
     *     'hash'     => 'sha256...',
     *   ]
     */
    public static function scan(?string $templatesDir = null): array
    {
        $dir = $templatesDir ?? ROOT_DIR . '/templates';

        if (!is_dir($dir)) {
            return [];
        }

        $templates = [];
        $categories = scandir($dir);

        foreach ($categories as $category) {
            if ($category === '.' || $category === '..' || !is_dir($dir . '/' . $category)) {
                continue;
            }

            $files = scandir($dir . '/' . $category);
            foreach ($files as $file) {
                if (!preg_match('/^(.+)-([a-z]{2})\.md$/', $file, $matches)) {
                    continue;
                }

                $filePath = $dir . '/' . $category . '/' . $file;
                if (!is_file($filePath)) {
                    continue;
                }

                $content = file_get_contents($filePath);
                if ($content === false) {
                    continue;
                }

                $key = $category . '/' . pathinfo($file, PATHINFO_FILENAME);
                $language = $matches[2];
                $title = self::extractTitle($content, $file);

                $templates[] = [
                    'key'      => $key,
                    'category' => $category,
                    'language' => $language,
                    'filename' => $file,
                    'title'    => $title,
                    'path'     => $filePath,
                    'hash'     => hash('sha256', $content),
                ];
            }
        }

        // Sort by category, then by key
        usort($templates, function ($a, $b) {
            $catCmp = strcmp($a['category'], $b['category']);
            return $catCmp !== 0 ? $catCmp : strcmp($a['key'], $b['key']);
        });

        return $templates;
    }

    /**
     * Get import status for all templates.
     *
     * Returns array keyed by template_key:
     *   [
     *     'template_key'  => 'scrum/daily-de',
     *     'content_hash'  => 'sha256...',
     *     'page_id'       => 42,
     *     'imported_at'   => '2025-01-01 12:00:00',
     *   ]
     */
    public static function getImportStatus(): array
    {
        try {
            $rows = DB::fetchAll('SELECT * FROM template_imports');
            $result = [];
            foreach ($rows as $row) {
                $result[$row['template_key']] = $row;
            }
            return $result;
        } catch (\Throwable $e) {
            // Table may not exist yet
            Logger::error('template_imports table not accessible', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Enrich templates with import status.
     *
     * Adds to each template:
     *   'status'     => 'not_imported' | 'imported' | 'update_available'
     *   'page_id'    => int|null
     *   'imported_at' => string|null
     */
    public static function getTemplatesWithStatus(?string $templatesDir = null): array
    {
        $templates = self::scan($templatesDir);
        $imports = self::getImportStatus();

        foreach ($templates as &$tpl) {
            if (!isset($imports[$tpl['key']])) {
                $tpl['status'] = 'not_imported';
                $tpl['page_id'] = null;
                $tpl['imported_at'] = null;
            } elseif ($imports[$tpl['key']]['content_hash'] !== $tpl['hash']) {
                $tpl['status'] = 'update_available';
                $tpl['page_id'] = (int) $imports[$tpl['key']]['page_id'];
                $tpl['imported_at'] = $imports[$tpl['key']]['imported_at'];
            } else {
                $tpl['status'] = 'imported';
                $tpl['page_id'] = (int) $imports[$tpl['key']]['page_id'];
                $tpl['imported_at'] = $imports[$tpl['key']]['imported_at'];
            }
        }
        unset($tpl);

        return $templates;
    }

    /**
     * Import a single template as a Page.
     *
     * Creates or finds the hierarchy:
     *   Templates (root) → Category → Template Page
     *
     * @return int The page ID of the imported template.
     */
    public static function importTemplate(array $template, int $userId, ?int $teamId = null): int
    {
        // 1. Ensure root "Templates" page exists
        $rootPageId = self::ensureRootPage($userId, $teamId);

        // 2. Ensure category page exists
        $categoryPageId = self::ensureCategoryPage($template['category'], $rootPageId, $userId, $teamId);

        // 3. Read template content
        $content = file_get_contents($template['path']);
        if ($content === false) {
            throw new RuntimeException('Cannot read template file: ' . $template['path']);
        }

        // 4. Check idempotency
        $existing = self::findImport($template['key']);
        if ($existing !== null && $existing['content_hash'] === $template['hash']) {
            // Already imported with same content
            return (int) $existing['page_id'];
        }

        // 5. Create the page
        $title = $template['title'];

        // If update: append "(Update)" suffix
        if ($existing !== null) {
            $title .= ' (Update)';
        }

        $pageId = Page::create([
            'title'      => $title,
            'parent_id'  => $categoryPageId,
            'content_md' => $content,
            'created_by' => $userId,
            'team_id'    => $teamId,
        ]);

        // 6. Record import
        self::recordImport($template['key'], $template['hash'], $pageId);

        return $pageId;
    }

    /**
     * Import multiple templates at once.
     *
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public static function importAll(array $templates, int $userId, ?int $teamId = null): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($templates as $tpl) {
            try {
                $existing = self::findImport($tpl['key']);
                if ($existing !== null && $existing['content_hash'] === $tpl['hash']) {
                    $result['skipped']++;
                    continue;
                }

                self::importTemplate($tpl, $userId, $teamId);
                $result['imported']++;
            } catch (\Throwable $e) {
                $result['errors'][] = $tpl['key'] . ': ' . $e->getMessage();
                Logger::error('Template import failed', [
                    'key'   => $tpl['key'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Import templates by language filter.
     *
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public static function importByLanguage(string $language, int $userId, ?int $teamId = null, ?string $templatesDir = null): array
    {
        $templates = self::scan($templatesDir);
        $filtered = array_filter($templates, fn($t) => $t['language'] === $language);
        return self::importAll($filtered, $userId, $teamId);
    }

    /**
     * Refresh templates from a ZIP archive (GitHub download).
     *
     * Downloads the ZIP, extracts /templates/ content, and replaces
     * the local /templates/ directory.
     *
     * @return array{success: bool, message: string}
     */
    public static function refreshFromGitHub(): array
    {
        $zipUrl = 'https://github.com/rueetschli/WorkPages/archive/refs/heads/main.zip';
        $allowedHost = 'github.com';
        $maxSize = 50 * 1024 * 1024; // 50 MB max

        // Validate URL host
        $parsedUrl = parse_url($zipUrl);
        if (!isset($parsedUrl['host']) || $parsedUrl['host'] !== $allowedHost) {
            return ['success' => false, 'message' => 'Invalid download URL host.'];
        }

        // Check ZipArchive availability
        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'message' => t('templates.zip_not_available')];
        }

        $tmpDir = ROOT_DIR . '/storage';
        $tmpFile = $tmpDir . '/templates_update_' . time() . '.zip';

        try {
            // Download ZIP
            $ctx = stream_context_create([
                'http' => [
                    'timeout'         => 30,
                    'max_redirects'   => 3,
                    'follow_location' => 1,
                    'user_agent'      => 'WorkPages/1.0',
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $data = @file_get_contents($zipUrl, false, $ctx);
            if ($data === false) {
                return ['success' => false, 'message' => t('templates.download_failed')];
            }

            if (strlen($data) > $maxSize) {
                return ['success' => false, 'message' => t('templates.zip_too_large')];
            }

            file_put_contents($tmpFile, $data);

            // Extract only /templates/ from ZIP
            $zip = new \ZipArchive();
            $res = $zip->open($tmpFile);
            if ($res !== true) {
                @unlink($tmpFile);
                return ['success' => false, 'message' => t('templates.zip_invalid')];
            }

            $targetDir = ROOT_DIR . '/templates';
            $extractedCount = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);

                // Match entries like WorkPages-main/templates/category/file.md
                if (!preg_match('#^[^/]+/templates/(.+\.md)$#', $entry, $m)) {
                    continue;
                }

                $relativePath = $m[1];

                // Security: prevent directory traversal
                if (str_contains($relativePath, '..')) {
                    continue;
                }

                $destPath = $targetDir . '/' . $relativePath;
                $destDir = dirname($destPath);

                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }

                $content = $zip->getFromIndex($i);
                if ($content !== false) {
                    file_put_contents($destPath, $content);
                    $extractedCount++;
                }
            }

            $zip->close();
            @unlink($tmpFile);

            if ($extractedCount === 0) {
                return ['success' => false, 'message' => t('templates.no_templates_in_zip')];
            }

            Logger::info('Templates refreshed from GitHub', ['count' => $extractedCount]);
            return ['success' => true, 'message' => t('templates.refresh_success', ['count' => $extractedCount])];

        } catch (\Throwable $e) {
            @unlink($tmpFile);
            Logger::error('GitHub template refresh failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => t('templates.refresh_failed')];
        }
    }

    // ── Internal helpers ────────────────────────────────────────────

    /**
     * Extract the title from a Markdown file (first H1), fallback to filename.
     */
    private static function extractTitle(string $content, string $filename): string
    {
        // Look for first H1: "# Title"
        if (preg_match('/^#\s+(.+)$/m', $content, $m)) {
            return trim($m[1]);
        }

        // Fallback: clean filename
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/-[a-z]{2}$/', '', $name); // remove language suffix
        $name = str_replace('-', ' ', $name);
        return ucwords($name);
    }

    /**
     * Ensure the root "Templates" page exists. Returns its ID.
     */
    private static function ensureRootPage(int $userId, ?int $teamId): int
    {
        // Look for an existing root page called "Templates"
        $row = DB::fetch(
            "SELECT id FROM pages WHERE title = 'Templates' AND parent_id IS NULL AND deleted_at IS NULL LIMIT 1"
        );

        if ($row) {
            return (int) $row['id'];
        }

        return Page::create([
            'title'      => 'Templates',
            'parent_id'  => null,
            'content_md' => t('templates.root_page_content'),
            'created_by' => $userId,
            'team_id'    => $teamId,
        ]);
    }

    /**
     * Ensure a category page exists under the root page. Returns its ID.
     */
    private static function ensureCategoryPage(string $category, int $rootPageId, int $userId, ?int $teamId): int
    {
        $title = ucfirst($category);

        $row = DB::fetch(
            'SELECT id FROM pages WHERE title = ? AND parent_id = ? AND deleted_at IS NULL LIMIT 1',
            [$title, $rootPageId]
        );

        if ($row) {
            return (int) $row['id'];
        }

        return Page::create([
            'title'      => $title,
            'parent_id'  => $rootPageId,
            'content_md' => '',
            'created_by' => $userId,
            'team_id'    => $teamId,
        ]);
    }

    /**
     * Find an existing import record by template key.
     */
    private static function findImport(string $key): ?array
    {
        try {
            return DB::fetch(
                'SELECT * FROM template_imports WHERE template_key = ?',
                [$key]
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Record a template import (insert or update).
     */
    private static function recordImport(string $key, string $hash, int $pageId): void
    {
        DB::query(
            'INSERT INTO template_imports (template_key, content_hash, page_id, imported_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE content_hash = VALUES(content_hash), page_id = VALUES(page_id), imported_at = NOW()',
            [$key, $hash, $pageId]
        );
    }

    /**
     * Get a human-readable category name.
     */
    public static function categoryLabel(string $category): string
    {
        $labels = [
            'scrum'         => 'Scrum',
            'meetings'      => t('templates.cat_meetings'),
            'organisation'  => t('templates.cat_organisation'),
            'documentation' => t('templates.cat_documentation'),
        ];

        return $labels[$category] ?? ucfirst($category);
    }
}
