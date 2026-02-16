<?php
/**
 * NotificationEngine - Resolves recipients, creates notifications, enqueues emails (AP15).
 *
 * This is the core of the notification pipeline. It maps events to recipients,
 * respects per-user settings, deduplicates, and excludes the actor.
 */
class NotificationEngine
{
    /** Priority levels. */
    const PRIORITY_HIGH   = 1;
    const PRIORITY_MEDIUM = 2;
    const PRIORITY_NORMAL = 3;
    const PRIORITY_LOW    = 4;

    /**
     * Handle an event from EventService::emit().
     */
    public static function handleEvent(array $event): void
    {
        $recipients = self::resolveRecipients($event);

        if (empty($recipients)) {
            return;
        }

        $notificationIds = self::createNotifications($recipients, $event);
        self::enqueueEmails($recipients, $event, $notificationIds);
    }

    /**
     * Resolve the list of users who should receive a notification.
     * Removes the actor (self-notification skip) and deduplicates.
     *
     * @return array<int, array{user_id: int, reason: string}>
     */
    public static function resolveRecipients(array $event): array
    {
        $eventName    = $event['name'];
        $entityType   = $event['entity_type'];
        $entityId     = $event['entity_id'];
        $actorUserId  = $event['actor_user_id'];
        $meta         = $event['meta'] ?? [];

        $recipients = []; // user_id => ['user_id' => int, 'reason' => string]

        // 1) Direct recipients based on event type
        switch ($eventName) {
            case 'mention.created':
                $mentionedId = (int) ($meta['mentioned_user_id'] ?? 0);
                if ($mentionedId > 0) {
                    $recipients[$mentionedId] = ['user_id' => $mentionedId, 'reason' => 'mention'];
                }
                break;

            case 'task.assigned':
                $newOwnerId = (int) ($meta['new_owner_id'] ?? 0);
                if ($newOwnerId > 0) {
                    $recipients[$newOwnerId] = ['user_id' => $newOwnerId, 'reason' => 'assignment'];
                }
                break;
        }

        // 2) Watcher recipients for entity-level events
        $watchEntityType = $entityType;
        $watchEntityId   = $entityId;

        // For comment events, resolve the parent entity
        if ($entityType === 'comment') {
            $watchEntityType = $meta['parent_entity_type'] ?? '';
            $watchEntityId   = (int) ($meta['parent_entity_id'] ?? 0);
        }

        if (in_array($watchEntityType, ['page', 'task'], true) && $watchEntityId > 0) {
            $watcherIds = Watcher::getWatcherIds($watchEntityType, $watchEntityId);
            foreach ($watcherIds as $wid) {
                if (!isset($recipients[$wid])) {
                    $recipients[$wid] = ['user_id' => $wid, 'reason' => 'watcher'];
                }
            }

            // Also notify task owner for task events
            if ($watchEntityType === 'task') {
                $task = Task::findById($watchEntityId);
                if ($task && !empty($task['owner_id'])) {
                    $ownerId = (int) $task['owner_id'];
                    if (!isset($recipients[$ownerId])) {
                        $recipients[$ownerId] = ['user_id' => $ownerId, 'reason' => 'owner'];
                    }
                }
            }
        }

        // 3) Remove the actor (no self-notification)
        unset($recipients[$actorUserId]);

        // 4) Filter by user notification settings
        $filtered = [];
        foreach ($recipients as $uid => $recipient) {
            try {
                $settings = NotificationSetting::getForUser($uid);
                if (NotificationSetting::wantsNotification($settings, $eventName)) {
                    $filtered[$uid] = $recipient;
                }
            } catch (\Throwable $e) {
                // If settings check fails, include the recipient
                $filtered[$uid] = $recipient;
            }
        }

        return $filtered;
    }

    /**
     * Create notification rows for each recipient.
     *
     * @return array<int, int>  Map of user_id => notification_id
     */
    public static function createNotifications(array $recipients, array $event): array
    {
        $eventName   = $event['name'];
        $entityType  = $event['entity_type'];
        $entityId    = $event['entity_id'];
        $actorUserId = $event['actor_user_id'];
        $meta        = $event['meta'] ?? [];

        $actor = User::findById($actorUserId);
        $actorName = $actor ? $actor['name'] : 'Unbekannt';

        $notifData = self::buildNotificationData($eventName, $entityType, $entityId, $actorName, $meta);

        $ids = [];

        foreach ($recipients as $uid => $recipient) {
            $dedupeKey = $eventName . ':' . $entityType . ':' . $entityId . ':' . $actorUserId . ':' . date('YmdHi');

            // Check dedupe
            if (Notification::existsByDedupeKey($uid, $dedupeKey)) {
                continue;
            }

            try {
                $notifId = Notification::create([
                    'user_id'       => $uid,
                    'type'          => $eventName,
                    'priority'      => $notifData['priority'],
                    'entity_type'   => $notifData['entity_type'],
                    'entity_id'     => $notifData['entity_id'],
                    'actor_user_id' => $actorUserId,
                    'title'         => $notifData['title'],
                    'body'          => $notifData['body'],
                    'url'           => $notifData['url'],
                    'dedupe_key'    => $dedupeKey,
                ]);

                $ids[$uid] = $notifId;
            } catch (\Throwable $e) {
                Logger::error('Failed to create notification', [
                    'user_id' => $uid,
                    'event'   => $eventName,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $ids;
    }

    /**
     * Enqueue emails for recipients whose settings allow immediate email.
     */
    public static function enqueueEmails(array $recipients, array $event, array $notificationIds): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');

        foreach ($recipients as $uid => $recipient) {
            if (!isset($notificationIds[$uid])) {
                continue;
            }

            try {
                $settings = NotificationSetting::getForUser($uid);

                if (empty($settings['email_enabled'])) {
                    continue;
                }

                if ($settings['email_mode'] !== 'immediate') {
                    // Digest modes handled by DigestService
                    continue;
                }

                $notif = Notification::findById($notificationIds[$uid]);
                if (!$notif) {
                    continue;
                }

                $user = User::findById($uid);
                if (!$user) {
                    continue;
                }

                $toEmail = !empty($settings['email_address_override'])
                    ? $settings['email_address_override']
                    : $user['email'];

                if (empty($toEmail)) {
                    continue;
                }

                $emailData = EmailService::renderNotificationEmail($notif, $user, $baseUrl);

                EmailOutbox::enqueue([
                    'user_id'         => $uid,
                    'notification_id' => $notificationIds[$uid],
                    'to_email'        => $toEmail,
                    'subject'         => $emailData['subject'],
                    'body_html'       => $emailData['body_html'],
                    'body_text'       => $emailData['body_text'],
                    'send_after'      => date('Y-m-d H:i:s'),
                ]);

                Notification::markEmailed([$notificationIds[$uid]]);

            } catch (\Throwable $e) {
                Logger::error('Failed to enqueue email', [
                    'user_id' => $uid,
                    'event'   => $event['name'],
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Build notification title, body, URL, and priority from event data.
     */
    private static function buildNotificationData(
        string $eventName,
        string $entityType,
        int $entityId,
        string $actorName,
        array $meta
    ): array {
        $baseUrl  = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        $title    = '';
        $body     = null;
        $url      = $baseUrl . '/?r=home';
        $priority = self::PRIORITY_NORMAL;

        // Resolve the notification entity type for storage
        $notifEntityType = $entityType;
        $notifEntityId   = $entityId;

        switch ($eventName) {
            case 'mention.created':
                $priority = self::PRIORITY_HIGH;
                $parentType = $meta['parent_entity_type'] ?? $entityType;
                $parentId   = (int) ($meta['parent_entity_id'] ?? $entityId);
                $notifEntityType = $parentType;
                $notifEntityId   = $parentId;

                if ($parentType === 'task') {
                    $task = Task::findById($parentId);
                    $title = $actorName . ' hat dich erwaehnt';
                    $body = $task ? 'In Aufgabe: ' . mb_substr($task['title'], 0, 100, 'UTF-8') : null;
                    $url = $baseUrl . '/?r=task_view&id=' . $parentId;
                } elseif ($parentType === 'page') {
                    $page = Page::findById($parentId);
                    $title = $actorName . ' hat dich erwaehnt';
                    $body = $page ? 'Auf Seite: ' . mb_substr($page['title'], 0, 100, 'UTF-8') : null;
                    $url = $baseUrl . '/?r=page_view&slug=' . urlencode($page['slug'] ?? '');
                } else {
                    $title = $actorName . ' hat dich erwaehnt';
                }
                break;

            case 'task.created':
                $title = $actorName . ' hat eine Aufgabe erstellt';
                $body = isset($meta['title']) ? mb_substr($meta['title'], 0, 200, 'UTF-8') : null;
                $url = $baseUrl . '/?r=task_view&id=' . $entityId;
                break;

            case 'task.updated':
                $title = $actorName . ' hat eine Aufgabe bearbeitet';
                $body = isset($meta['title']) ? mb_substr($meta['title'], 0, 200, 'UTF-8') : null;
                $url = $baseUrl . '/?r=task_view&id=' . $entityId;
                break;

            case 'task.assigned':
                $priority = self::PRIORITY_HIGH;
                $title = $actorName . ' hat dir eine Aufgabe zugewiesen';
                $body = isset($meta['task_title']) ? mb_substr($meta['task_title'], 0, 200, 'UTF-8') : null;
                $taskId = (int) ($meta['task_id'] ?? $entityId);
                $notifEntityId = $taskId;
                $url = $baseUrl . '/?r=task_view&id=' . $taskId;
                break;

            case 'task.moved':
                $priority = self::PRIORITY_LOW;
                $title = $actorName . ' hat eine Aufgabe verschoben';
                $newCol = $meta['new_column_name'] ?? '';
                $body = $newCol !== '' ? ('Neue Spalte: ' . mb_substr($newCol, 0, 100, 'UTF-8')) : null;
                $url = $baseUrl . '/?r=task_view&id=' . $entityId;
                break;

            case 'task.commented':
                $priority = self::PRIORITY_MEDIUM;
                $parentId = (int) ($meta['parent_entity_id'] ?? $entityId);
                $notifEntityType = 'task';
                $notifEntityId = $parentId;
                $task = Task::findById($parentId);
                $title = $actorName . ' hat kommentiert';
                $body = $task ? 'Aufgabe: ' . mb_substr($task['title'], 0, 200, 'UTF-8') : null;
                $anchor = isset($meta['comment_id']) ? '#comment-' . $meta['comment_id'] : '';
                $url = $baseUrl . '/?r=task_view&id=' . $parentId . $anchor;
                break;

            case 'page.created':
                $title = $actorName . ' hat eine Seite erstellt';
                $body = isset($meta['title']) ? mb_substr($meta['title'], 0, 200, 'UTF-8') : null;
                $page = Page::findById($entityId);
                $url = $page
                    ? $baseUrl . '/?r=page_view&slug=' . urlencode($page['slug'])
                    : $baseUrl . '/?r=pages';
                break;

            case 'page.updated':
                $title = $actorName . ' hat eine Seite bearbeitet';
                $body = isset($meta['title']) ? mb_substr($meta['title'], 0, 200, 'UTF-8') : null;
                $page = Page::findById($entityId);
                $url = $page
                    ? $baseUrl . '/?r=page_view&slug=' . urlencode($page['slug'])
                    : $baseUrl . '/?r=pages';
                break;

            case 'page.commented':
                $priority = self::PRIORITY_MEDIUM;
                $parentId = (int) ($meta['parent_entity_id'] ?? $entityId);
                $notifEntityType = 'page';
                $notifEntityId = $parentId;
                $page = Page::findById($parentId);
                $title = $actorName . ' hat kommentiert';
                $body = $page ? 'Seite: ' . mb_substr($page['title'], 0, 200, 'UTF-8') : null;
                $anchor = isset($meta['comment_id']) ? '#comment-' . $meta['comment_id'] : '';
                $url = $page
                    ? $baseUrl . '/?r=page_view&slug=' . urlencode($page['slug']) . $anchor
                    : $baseUrl . '/?r=pages';
                break;

            case 'page_task.linked':
                $title = $actorName . ' hat eine Aufgabe verknuepft';
                $body = isset($meta['task_title']) ? mb_substr($meta['task_title'], 0, 200, 'UTF-8') : null;
                $page = Page::findById($entityId);
                $url = $page
                    ? $baseUrl . '/?r=page_view&slug=' . urlencode($page['slug'])
                    : $baseUrl . '/?r=pages';
                break;

            case 'page_task.unlinked':
                $title = $actorName . ' hat eine Verknuepfung entfernt';
                $body = isset($meta['task_title']) ? mb_substr($meta['task_title'], 0, 200, 'UTF-8') : null;
                $page = Page::findById($entityId);
                $url = $page
                    ? $baseUrl . '/?r=page_view&slug=' . urlencode($page['slug'])
                    : $baseUrl . '/?r=pages';
                break;

            default:
                $title = $actorName . ' hat eine Aktion ausgefuehrt';
                break;
        }

        return [
            'title'       => $title,
            'body'        => $body,
            'url'         => $url,
            'priority'    => $priority,
            'entity_type' => $notifEntityType,
            'entity_id'   => $notifEntityId,
        ];
    }
}
