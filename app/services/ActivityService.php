<?php
/**
 * ActivityService - Central service for activity logging and formatting.
 *
 * Provides standardised action names, logging, retrieval, and
 * human-readable formatting of activity entries.
 */
class ActivityService
{
    /**
     * Log an activity entry with standardised action name.
     */
    public static function log(string $entityType, int $entityId, string $action, int $createdBy, ?array $meta = null): void
    {
        try {
            $metaJson = null;
            if ($meta !== null) {
                $mode = $GLOBALS['config']['ACTIVITY_META_MODE'] ?? 'text';
                $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            DB::query(
                'INSERT INTO activity (entity_type, entity_id, action, meta_json, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [
                    $entityType,
                    $entityId,
                    $action,
                    $metaJson,
                    $createdBy,
                ]
            );
        } catch (Throwable $e) {
            Logger::error('Failed to log activity', [
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'action'      => $action,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch recent activity entries for a given entity.
     *
     * @return array<int, array>
     */
    public static function listFor(string $entityType, int $entityId, int $limit = 50): array
    {
        return DB::fetchAll(
            'SELECT a.*, u.name AS user_name
             FROM activity a
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.created_at DESC
             LIMIT ' . (int) $limit,
            [$entityType, $entityId]
        );
    }

    /**
     * Format an activity row into a human-readable German text.
     *
     * @param array $row  A row from listFor()
     * @return string  HTML-safe, human-readable description
     */
    public static function formatActivity(array $row): string
    {
        $meta = [];
        if (!empty($row['meta_json'])) {
            $decoded = json_decode($row['meta_json'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $user = Security::esc($row['user_name'] ?? 'Unbekannt');
        $action = $row['action'] ?? '';

        switch ($action) {
            // Pages
            case 'page_created':
                $title = isset($meta['title']) ? ' "' . Security::esc($meta['title']) . '"' : '';
                return $user . ' hat die Seite' . $title . ' erstellt';

            case 'page_updated':
                return $user . ' hat die Seite bearbeitet';

            case 'page_deleted':
                $title = isset($meta['title']) ? ' "' . Security::esc($meta['title']) . '"' : '';
                return $user . ' hat die Seite' . $title . ' geloescht';

            // Tasks
            case 'task_created':
                $title = isset($meta['title']) ? ' "' . Security::esc($meta['title']) . '"' : '';
                return $user . ' hat die Aufgabe' . $title . ' erstellt';

            case 'task_updated':
                $fields = $meta['changed_fields'] ?? [];
                if (!empty($fields)) {
                    $fieldNames = array_map(function ($f) {
                        return Security::esc(self::fieldLabel($f));
                    }, $fields);
                    return $user . ' hat die Aufgabe bearbeitet (' . implode(', ', $fieldNames) . ')';
                }
                return $user . ' hat die Aufgabe bearbeitet';

            case 'task_deleted':
                $title = isset($meta['title']) ? ' "' . Security::esc($meta['title']) . '"' : '';
                return $user . ' hat die Aufgabe' . $title . ' geloescht';

            case 'task_status_changed':
                $old = isset($meta['old_status']) ? Security::esc($meta['old_status']) : '?';
                $new = isset($meta['new_status']) ? Security::esc($meta['new_status']) : '?';
                $via = !empty($meta['board']) ? ' (Board)' : '';
                return $user . ' hat den Status von ' . $old . ' nach ' . $new . ' geaendert' . $via;

            case 'task_owner_changed':
                $newOwner = isset($meta['new_owner']) ? Security::esc($meta['new_owner']) : 'Nicht zugewiesen';
                return $user . ' hat den Owner auf ' . $newOwner . ' geaendert';

            case 'task_due_changed':
                $newDue = isset($meta['new_due']) ? Security::esc($meta['new_due']) : 'Kein Datum';
                return $user . ' hat das Faelligkeitsdatum auf ' . $newDue . ' geaendert';

            case 'task_tags_changed':
                return $user . ' hat die Tags geaendert';

            // Relations
            case 'page_task_linked':
                $taskTitle = isset($meta['task_title']) ? ' "' . Security::esc($meta['task_title']) . '"' : '';
                return $user . ' hat die Aufgabe' . $taskTitle . ' verknuepft';

            case 'page_task_unlinked':
                $taskTitle = isset($meta['task_title']) ? ' "' . Security::esc($meta['task_title']) . '"' : '';
                return $user . ' hat die Verknuepfung zur Aufgabe' . $taskTitle . ' entfernt';

            // Comments
            case 'comment_created':
                return $user . ' hat einen Kommentar hinzugefuegt';

            case 'comment_deleted':
                return $user . ' hat einen Kommentar geloescht';

            // Legacy action names (backward compatibility with pre-AP8 data)
            case 'created':
                $title = isset($meta['title']) ? ' "' . Security::esc($meta['title']) . '"' : '';
                return $user . ' hat' . $title . ' erstellt';

            case 'updated':
                return $user . ' hat bearbeitet';

            case 'deleted':
                $title = isset($meta['title']) ? ' "' . Security::esc($meta['title']) . '"' : '';
                return $user . ' hat' . $title . ' geloescht';

            case 'status_changed':
                $old = isset($meta['old_status']) ? Security::esc($meta['old_status']) : '?';
                $new = isset($meta['new_status']) ? Security::esc($meta['new_status']) : '?';
                return $user . ' hat den Status von ' . $old . ' nach ' . $new . ' geaendert';

            case 'task_linked':
                $taskTitle = isset($meta['task_title']) ? ' "' . Security::esc($meta['task_title']) . '"' : '';
                return $user . ' hat die Aufgabe' . $taskTitle . ' verknuepft';

            case 'task_unlinked':
                $taskTitle = isset($meta['task_title']) ? ' "' . Security::esc($meta['task_title']) . '"' : '';
                return $user . ' hat die Verknuepfung zur Aufgabe' . $taskTitle . ' entfernt';

            default:
                return $user . ' hat eine Aktion ausgefuehrt (' . Security::esc($action) . ')';
        }
    }

    /**
     * Translate a database field name to a human-readable German label.
     */
    private static function fieldLabel(string $field): string
    {
        $labels = [
            'title'          => 'Titel',
            'description_md' => 'Beschreibung',
            'status'         => 'Status',
            'owner_id'       => 'Owner',
            'due_date'       => 'Faelligkeitsdatum',
            'content_md'     => 'Inhalt',
            'parent_id'      => 'Uebergeordnete Seite',
            'tags'           => 'Tags',
        ];

        return $labels[$field] ?? $field;
    }
}
