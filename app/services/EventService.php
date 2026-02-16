<?php
/**
 * EventService - Central event emitter for the notification pipeline (AP15).
 *
 * Controllers call EventService::emit() after successful write operations.
 * The engine resolves recipients and creates notifications + email outbox entries.
 */
class EventService
{
    /**
     * Emit an application event.
     *
     * @param string $eventName    e.g. 'task.created', 'mention.created', 'page.commented'
     * @param string $entityType   'page', 'task', or 'comment'
     * @param int    $entityId     ID of the entity
     * @param int    $actorUserId  ID of the user who triggered the event
     * @param array  $meta         Additional event data (varies by event type)
     */
    public static function emit(string $eventName, string $entityType, int $entityId, int $actorUserId, array $meta = []): void
    {
        try {
            $event = [
                'name'          => $eventName,
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'actor_user_id' => $actorUserId,
                'meta'          => $meta,
            ];

            NotificationEngine::handleEvent($event);
        } catch (\Throwable $e) {
            Logger::error('EventService::emit failed', [
                'event'  => $eventName,
                'entity' => $entityType . ':' . $entityId,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
