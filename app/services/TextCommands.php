<?php
/**
 * TextCommands - Central parser for Smart Text Commands (AP14).
 *
 * Handles extraction and execution of:
 *   @mentions  - @[Display Name](user:ID) tokens
 *   #tags      - #tag-name references
 *   /commands  - /task, /due, /assign inline commands
 *
 * Token formats:
 *   Mention: @[Display Name](user:123)
 *   Tag:     #tag-name (lowercase, letters/digits/dot/underscore/hyphen)
 *   Command: /command arguments (must be at line start or after whitespace)
 */
class TextCommands
{
    /** Regex for mention tokens: @[Name](user:ID) */
    const MENTION_PATTERN = '/@\[([^\]]+)\]\(user:(\d+)\)/';

    /** Regex for tag references: #tag-name */
    const TAG_PATTERN = '/(?:^|(?<=\s))#([a-z0-9][a-z0-9._-]{0,49})(?=\s|$|[.,;:!?)])/m';

    /** Regex for /commands at start of line */
    const COMMAND_PATTERN = '/^\/([a-z]+)\s+(.+)$/m';

    // ── Extraction ───────────────────────────────────────────────────

    /**
     * Extract mentioned user IDs from Markdown text.
     *
     * @param string $md  The Markdown text
     * @return int[]      Array of user IDs
     */
    public static function extractMentions(string $md): array
    {
        $ids = [];
        if (preg_match_all(self::MENTION_PATTERN, $md, $matches)) {
            foreach ($matches[2] as $id) {
                $ids[] = (int) $id;
            }
        }
        return array_unique($ids);
    }

    /**
     * Extract referenced tag names from Markdown text.
     *
     * @param string $md  The Markdown text
     * @return string[]   Array of tag names (lowercase)
     */
    public static function extractTagRefs(string $md): array
    {
        $tags = [];
        if (preg_match_all(self::TAG_PATTERN, $md, $matches)) {
            foreach ($matches[1] as $tag) {
                $tags[] = mb_strtolower($tag, 'UTF-8');
            }
        }
        return array_unique($tags);
    }

    /**
     * Extract /commands from Markdown text.
     *
     * @param string $md       The Markdown text
     * @param string $context  'page', 'task', or 'comment'
     * @return array  Array of ['command' => string, 'args' => string, 'line' => string]
     */
    public static function extractCommands(string $md, string $context): array
    {
        $commands = [];
        $allowed = self::allowedCommands($context);

        if (preg_match_all(self::COMMAND_PATTERN, $md, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $cmd = $m[1];
                $args = trim($m[2]);
                if (in_array($cmd, $allowed, true) && $args !== '') {
                    $commands[] = [
                        'command' => $cmd,
                        'args'    => $args,
                        'line'    => $m[0],
                    ];
                }
            }
        }

        return $commands;
    }

    /**
     * Get allowed commands for a given context.
     *
     * @return string[]
     */
    public static function allowedCommands(string $context): array
    {
        switch ($context) {
            case 'page':
                return ['task'];
            case 'task':
                return ['due', 'assign', 'tag'];
            case 'comment':
                return [];
            default:
                return [];
        }
    }

    // ── Text Cleaning ────────────────────────────────────────────────

    /**
     * Strip command lines from text without executing them.
     * Useful when you need the cleaned text before the entity exists.
     *
     * @param string $md       The Markdown text
     * @param string $context  'page', 'task', or 'comment'
     * @return string  Cleaned text with command lines removed
     */
    public static function stripCommands(string $md, string $context): string
    {
        $commands = self::extractCommands($md, $context);
        if (empty($commands)) {
            return $md;
        }

        $commandLines = [];
        foreach ($commands as $cmd) {
            $commandLines[] = trim($cmd['line']);
        }

        $lines = explode("\n", $md);
        $cleanedLines = [];
        foreach ($lines as $l) {
            $trimmed = trim($l);
            if (!in_array($trimmed, $commandLines, true)) {
                $cleanedLines[] = $l;
            }
        }

        $cleaned = implode("\n", $cleanedLines);
        $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned);
        return trim($cleaned);
    }

    // ── Command Execution ────────────────────────────────────────────

    /**
     * Process all text commands in Markdown content.
     * Executes valid commands, removes command lines from text,
     * and returns the cleaned text plus execution results.
     *
     * @param string $md       The Markdown text
     * @param string $context  'page', 'task', or 'comment'
     * @param int    $userId   ID of the user performing the save
     * @param array  $params   Context-specific parameters:
     *                         - page_id: for page context
     *                         - task_id: for task context
     *                         - page_slug: for page context (redirect)
     * @return array ['text' => cleaned text, 'results' => array of result messages]
     */
    public static function processCommands(string $md, string $context, int $userId, array $params = []): array
    {
        $commands = self::extractCommands($md, $context);
        $results = [];
        $linesToRemove = [];

        foreach ($commands as $cmd) {
            $result = self::executeCommand($cmd, $context, $userId, $params);
            if ($result !== null) {
                $results[] = $result;
            }
            // Always remove command lines from text
            $linesToRemove[] = $cmd['line'];
        }

        // Remove command lines from text (line-by-line to avoid substring matches)
        $lines = explode("\n", $md);
        $cleanedLines = [];
        foreach ($lines as $l) {
            $trimmed = trim($l);
            $isCommand = false;
            foreach ($linesToRemove as $cmdLine) {
                if ($trimmed === trim($cmdLine)) {
                    $isCommand = true;
                    break;
                }
            }
            if (!$isCommand) {
                $cleanedLines[] = $l;
            }
        }
        $cleanedText = implode("\n", $cleanedLines);

        // Clean up empty lines left by removed commands
        $cleanedText = preg_replace('/\n{3,}/', "\n\n", $cleanedText);
        $cleanedText = trim($cleanedText);

        return [
            'text'    => $cleanedText,
            'results' => $results,
        ];
    }

    /**
     * Execute a single command.
     *
     * @return array|null  Result message or null on failure
     */
    private static function executeCommand(array $cmd, string $context, int $userId, array $params): ?array
    {
        switch ($cmd['command']) {
            case 'task':
                return self::executeTaskCommand($cmd['args'], $userId, $params);
            case 'due':
                return self::executeDueCommand($cmd['args'], $userId, $params);
            case 'assign':
                return self::executeAssignCommand($cmd['args'], $userId, $params);
            case 'tag':
                return self::executeTagCommand($cmd['args'], $userId, $params);
            default:
                return null;
        }
    }

    /**
     * /task Title - Create a new task and optionally link to current page.
     */
    private static function executeTaskCommand(string $title, int $userId, array $params): ?array
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title, 'UTF-8') > 190) {
            return ['type' => 'error', 'message' => '/task: Titel ungueltig oder zu lang.'];
        }

        try {
            $defaultColumnId = BoardColumn::getDefaultId();
            $defaultColumn = BoardColumn::findById($defaultColumnId);

            $taskId = Task::create([
                'title'          => $title,
                'description_md' => null,
                'column_id'      => $defaultColumnId,
                'owner_id'       => null,
                'due_date'       => null,
                'created_by'     => $userId,
            ]);

            ActivityService::log('task', $taskId, 'task_created', $userId, [
                'title'       => $title,
                'column_name' => $defaultColumn ? $defaultColumn['name'] : '',
                'via_command' => true,
            ]);

            // Link to page if in page context
            if (!empty($params['page_id'])) {
                $pageId = (int) $params['page_id'];
                PageTask::addTask($pageId, $taskId, $userId);

                $page = Page::findById($pageId);
                ActivityService::log('page', $pageId, 'page_task_linked', $userId, [
                    'task_id'    => $taskId,
                    'task_title' => $title,
                    'page_title' => $page ? $page['title'] : '',
                    'via_command' => true,
                ]);
            }

            return [
                'type'    => 'success',
                'message' => 'Aufgabe "' . $title . '" erstellt.',
                'task_id' => $taskId,
            ];
        } catch (Throwable $e) {
            Logger::error('/task command failed', ['error' => $e->getMessage(), 'title' => $title]);
            return ['type' => 'error', 'message' => '/task: Aufgabe konnte nicht erstellt werden.'];
        }
    }

    /**
     * /due YYYY-MM-DD - Set due date on current task.
     */
    private static function executeDueCommand(string $dateStr, int $userId, array $params): ?array
    {
        $dateStr = trim($dateStr);
        $d = date_create_from_format('Y-m-d', $dateStr);
        if (!$d || $d->format('Y-m-d') !== $dateStr) {
            return ['type' => 'error', 'message' => '/due: Ungueltiges Datum (YYYY-MM-DD erwartet).'];
        }

        if (empty($params['task_id'])) {
            return ['type' => 'error', 'message' => '/due: Nur im Task-Kontext verfuegbar.'];
        }

        try {
            $taskId = (int) $params['task_id'];
            $task = Task::findById($taskId);
            if (!$task) {
                return ['type' => 'error', 'message' => '/due: Aufgabe nicht gefunden.'];
            }

            $oldDue = $task['due_date'];
            Task::update($taskId, [
                'due_date'   => $dateStr,
                'updated_by' => $userId,
            ]);

            ActivityService::log('task', $taskId, 'task_due_changed', $userId, [
                'old_due'     => $oldDue,
                'new_due'     => $dateStr,
                'via_command' => true,
            ]);

            return [
                'type'    => 'success',
                'message' => 'Faelligkeitsdatum auf ' . $dateStr . ' gesetzt.',
            ];
        } catch (Throwable $e) {
            Logger::error('/due command failed', ['error' => $e->getMessage()]);
            return ['type' => 'error', 'message' => '/due: Datum konnte nicht gesetzt werden.'];
        }
    }

    /**
     * /assign @[Name](user:ID) - Set task owner.
     */
    private static function executeAssignCommand(string $args, int $userId, array $params): ?array
    {
        // Extract user ID from mention token or plain number
        $assignUserId = null;
        if (preg_match(self::MENTION_PATTERN, $args, $m)) {
            $assignUserId = (int) $m[2];
        } elseif (ctype_digit(trim($args))) {
            $assignUserId = (int) trim($args);
        }

        if ($assignUserId === null) {
            return ['type' => 'error', 'message' => '/assign: Benutzer-Referenz ungueltig. Verwende @-Mention.'];
        }

        if (empty($params['task_id'])) {
            return ['type' => 'error', 'message' => '/assign: Nur im Task-Kontext verfuegbar.'];
        }

        $user = User::findById($assignUserId);
        if (!$user) {
            return ['type' => 'error', 'message' => '/assign: Benutzer nicht gefunden.'];
        }

        try {
            $taskId = (int) $params['task_id'];
            Task::update($taskId, [
                'owner_id'   => $assignUserId,
                'updated_by' => $userId,
            ]);

            ActivityService::log('task', $taskId, 'task_owner_changed', $userId, [
                'new_owner'   => $user['name'],
                'via_command' => true,
            ]);

            return [
                'type'    => 'success',
                'message' => 'Owner auf ' . $user['name'] . ' gesetzt.',
            ];
        } catch (Throwable $e) {
            Logger::error('/assign command failed', ['error' => $e->getMessage()]);
            return ['type' => 'error', 'message' => '/assign: Owner konnte nicht gesetzt werden.'];
        }
    }

    /**
     * /tag tag-name - Add tag to current task.
     */
    private static function executeTagCommand(string $tagName, int $userId, array $params): ?array
    {
        $tagName = mb_strtolower(trim($tagName), 'UTF-8');
        // Remove leading # if present
        if (str_starts_with($tagName, '#')) {
            $tagName = substr($tagName, 1);
        }

        if ($tagName === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{0,49}$/', $tagName)) {
            return ['type' => 'error', 'message' => '/tag: Ungueltiger Tag-Name.'];
        }

        if (empty($params['task_id'])) {
            return ['type' => 'error', 'message' => '/tag: Nur im Task-Kontext verfuegbar.'];
        }

        try {
            $taskId = (int) $params['task_id'];
            $existingTags = Task::getTags($taskId);
            $existingNames = array_column($existingTags, 'name');

            if (!in_array($tagName, $existingNames, true)) {
                $existingNames[] = $tagName;
                Task::setTags($taskId, $existingNames);

                ActivityService::log('task', $taskId, 'task_tags_changed', $userId, [
                    'added_tag'   => $tagName,
                    'via_command' => true,
                ]);
            }

            return [
                'type'    => 'success',
                'message' => 'Tag "' . $tagName . '" hinzugefuegt.',
            ];
        } catch (Throwable $e) {
            Logger::error('/tag command failed', ['error' => $e->getMessage()]);
            return ['type' => 'error', 'message' => '/tag: Tag konnte nicht hinzugefuegt werden.'];
        }
    }

    // ── Mention Synchronisation ──────────────────────────────────────

    /**
     * Synchronise mentions for an entity based on parsed text.
     *
     * @param string $md         The Markdown text (after command processing)
     * @param string $entityType 'page', 'task', or 'comment'
     * @param int    $entityId   Entity ID
     * @param int    $userId     User performing the save
     */
    public static function syncMentions(string $md, string $entityType, int $entityId, int $userId): void
    {
        $mentionedIds = self::extractMentions($md);
        Mention::sync($entityType, $entityId, $mentionedIds, $userId);
    }
}
