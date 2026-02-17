<?php
/**
 * NotificationSetting model - per-user notification preferences (AP15).
 *
 * Settings are lazy-created: a default row is inserted on first access.
 */
class NotificationSetting
{
    /** Default settings for new users. */
    private const DEFAULTS = [
        'email_enabled'          => 1,
        'email_mode'             => 'immediate',
        'email_address_override' => null,
        'watch_auto_on_create'   => 1,
        'watch_auto_on_comment'  => 1,
        'notify_on_task_updates' => 1,
        'notify_on_page_updates' => 1,
        'notify_on_comments'     => 1,
        'notify_on_mentions'     => 1,
        'notify_on_assignments'  => 1,
        'notify_on_moves'        => 0,
    ];

    /**
     * Get settings for a user, creating defaults if not present.
     */
    public static function getForUser(int $userId): array
    {
        $row = DB::fetch(
            'SELECT * FROM notification_settings WHERE user_id = ?',
            [$userId]
        );

        if ($row) {
            return $row;
        }

        // Lazy create defaults
        self::ensureDefaults($userId);

        $row = DB::fetch(
            'SELECT * FROM notification_settings WHERE user_id = ?',
            [$userId]
        );

        return $row ?: array_merge(['user_id' => $userId], self::DEFAULTS);
    }

    /**
     * Insert default settings for a user if they do not exist.
     */
    public static function ensureDefaults(int $userId): void
    {
        $exists = DB::fetch(
            'SELECT user_id FROM notification_settings WHERE user_id = ?',
            [$userId]
        );

        if ($exists) {
            return;
        }

        DB::query(
            'INSERT INTO notification_settings
                (user_id, email_enabled, email_mode, email_address_override,
                 watch_auto_on_create, watch_auto_on_comment,
                 notify_on_task_updates, notify_on_page_updates,
                 notify_on_comments, notify_on_mentions,
                 notify_on_assignments, notify_on_moves)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                self::DEFAULTS['email_enabled'],
                self::DEFAULTS['email_mode'],
                self::DEFAULTS['email_address_override'],
                self::DEFAULTS['watch_auto_on_create'],
                self::DEFAULTS['watch_auto_on_comment'],
                self::DEFAULTS['notify_on_task_updates'],
                self::DEFAULTS['notify_on_page_updates'],
                self::DEFAULTS['notify_on_comments'],
                self::DEFAULTS['notify_on_mentions'],
                self::DEFAULTS['notify_on_assignments'],
                self::DEFAULTS['notify_on_moves'],
            ]
        );
    }

    /**
     * Update settings for a user.
     */
    public static function update(int $userId, array $data): void
    {
        self::ensureDefaults($userId);

        $allowed = [
            'email_enabled', 'email_mode', 'email_address_override',
            'watch_auto_on_create', 'watch_auto_on_comment',
            'notify_on_task_updates', 'notify_on_page_updates',
            'notify_on_comments', 'notify_on_mentions',
            'notify_on_assignments', 'notify_on_moves',
        ];

        $sets = [];
        $params = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($sets)) {
            return;
        }

        $params[] = $userId;
        DB::query(
            'UPDATE notification_settings SET ' . implode(', ', $sets) . ' WHERE user_id = ?',
            $params
        );
    }

    /**
     * Check if a user wants notifications for a given event type.
     */
    public static function wantsNotification(array $settings, string $eventType): bool
    {
        $map = [
            'mention.created'    => 'notify_on_mentions',
            'task.assigned'      => 'notify_on_assignments',
            'task.created'       => 'notify_on_task_updates',
            'task.updated'       => 'notify_on_task_updates',
            'task.moved'         => 'notify_on_moves',
            'task.commented'     => 'notify_on_comments',
            'page.created'       => 'notify_on_page_updates',
            'page.updated'       => 'notify_on_page_updates',
            'page.commented'     => 'notify_on_comments',
            'page_task.linked'   => 'notify_on_task_updates',
            'page_task.unlinked' => 'notify_on_task_updates',
            // AP17: attachment.uploaded not mapped = always notify watchers (no separate setting)
        ];

        $settingKey = $map[$eventType] ?? null;
        if ($settingKey === null) {
            return true;
        }

        return !empty($settings[$settingKey]);
    }
}
