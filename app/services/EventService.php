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

        // AP23: Process pending emails immediately after enqueue.
        // Without this, emails sit in email_outbox with status 'pending'
        // until an admin manually triggers sending. This ensures emails
        // are delivered right after they are created.
        try {
            EmailService::processPending(5);
        } catch (\Throwable $e) {
            Logger::error('EventService email processing failed', [
                'event' => $eventName,
                'error' => $e->getMessage(),
            ]);
        }

        // AP19: Enqueue webhook deliveries for supported events
        try {
            if (in_array($eventName, WebhookService::EVENTS, true)) {
                // Resolve team_id from entity
                $teamId = null;
                if ($entityType === 'task') {
                    $entity = Task::findById($entityId);
                    $teamId = $entity ? ($entity['team_id'] ?? null) : null;
                    if ($teamId !== null) {
                        $teamId = (int) $teamId;
                    }
                } elseif ($entityType === 'page') {
                    $entity = Page::findById($entityId);
                    $teamId = $entity ? ($entity['team_id'] ?? null) : null;
                    if ($teamId !== null) {
                        $teamId = (int) $teamId;
                    }
                }

                $payload = WebhookService::buildPayload(
                    $eventName,
                    $entityType,
                    $entityId,
                    $actorUserId,
                    $teamId,
                    $meta
                );

                WebhookService::enqueue($eventName, $payload, $teamId);
            }
        } catch (\Throwable $e) {
            Logger::error('EventService webhook enqueue failed', [
                'event' => $eventName,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
