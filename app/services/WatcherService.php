<?php
/**
 * WatcherService - Auto-watch logic and watcher management (AP15).
 *
 * Handles automatic watcher creation based on user actions and settings.
 * Auto-watch rules:
 *   - Creating a page/task: auto-watch (if watch_auto_on_create enabled)
 *   - Commenting on page/task: auto-watch (if watch_auto_on_comment enabled)
 *   - Becoming task owner: auto-watch
 *
 * After explicit unwatch, auto-watch only re-triggers on new explicit actions
 * (commenting again, not on passive events).
 */
class WatcherService
{
    /**
     * Auto-watch on entity creation.
     */
    public static function autoWatchOnCreate(string $entityType, int $entityId, int $userId): void
    {
        try {
            $settings = NotificationSetting::getForUser($userId);
            if (!empty($settings['watch_auto_on_create'])) {
                Watcher::watch($entityType, $entityId, $userId);
            }
        } catch (\Throwable $e) {
            Logger::error('autoWatchOnCreate failed', [
                'entity' => $entityType . ':' . $entityId,
                'user'   => $userId,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Auto-watch on comment creation.
     */
    public static function autoWatchOnComment(string $entityType, int $entityId, int $userId): void
    {
        try {
            $settings = NotificationSetting::getForUser($userId);
            if (!empty($settings['watch_auto_on_comment'])) {
                Watcher::watch($entityType, $entityId, $userId);
            }
        } catch (\Throwable $e) {
            Logger::error('autoWatchOnComment failed', [
                'entity' => $entityType . ':' . $entityId,
                'user'   => $userId,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Auto-watch on task assignment (new owner watches the task).
     */
    public static function autoWatchOnAssignment(int $taskId, int $newOwnerId): void
    {
        try {
            Watcher::watch('task', $taskId, $newOwnerId);
        } catch (\Throwable $e) {
            Logger::error('autoWatchOnAssignment failed', [
                'task'  => $taskId,
                'owner' => $newOwnerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
