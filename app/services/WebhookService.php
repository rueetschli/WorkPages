<?php
/**
 * WebhookService - Webhook endpoint management and event enqueuing (AP19).
 *
 * Enqueues webhook deliveries into webhook_outbox. Never blocks UI requests.
 */
class WebhookService
{
    /**
     * All supported webhook event names.
     */
    public const EVENTS = [
        'task.created',
        'task.updated',
        'task.assigned',
        'task.moved',
        'task.done',
        'task.deleted',
        'comment.created',
        'attachment.added',
        'page.created',
        'page.updated',
        'page.deleted',
    ];

    // ── Endpoint CRUD ────────────────────────────────────────────────

    /**
     * Create a webhook endpoint.
     *
     * @return int  The new endpoint ID
     */
    public static function createEndpoint(array $data): int
    {
        $secret = bin2hex(random_bytes(32));

        DB::query(
            'INSERT INTO webhook_endpoints (team_id, created_by, name, url, secret, events, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())',
            [
                !empty($data['team_id']) ? (int) $data['team_id'] : null,
                (int) $data['created_by'],
                $data['name'],
                $data['url'],
                $secret,
                $data['events'],
            ]
        );

        return (int) DB::lastInsertId();
    }

    /**
     * Update a webhook endpoint.
     */
    public static function updateEndpoint(int $id, array $data): void
    {
        $sets = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $sets[] = 'name = ?';
            $params[] = $data['name'];
        }
        if (array_key_exists('url', $data)) {
            $sets[] = 'url = ?';
            $params[] = $data['url'];
        }
        if (array_key_exists('events', $data)) {
            $sets[] = 'events = ?';
            $params[] = $data['events'];
        }
        if (array_key_exists('is_active', $data)) {
            $sets[] = 'is_active = ?';
            $params[] = (int) $data['is_active'];
        }

        if (empty($sets)) {
            return;
        }

        $sets[] = 'updated_at = NOW()';
        $params[] = $id;

        DB::query(
            'UPDATE webhook_endpoints SET ' . implode(', ', $sets) . ' WHERE id = ?',
            $params
        );
    }

    /**
     * Delete a webhook endpoint and its outbox entries.
     */
    public static function deleteEndpoint(int $id): void
    {
        DB::query('DELETE FROM webhook_outbox WHERE webhook_endpoint_id = ?', [$id]);
        DB::query('DELETE FROM webhook_endpoints WHERE id = ?', [$id]);
    }

    /**
     * Find an endpoint by ID.
     */
    public static function findEndpoint(int $id): ?array
    {
        return DB::fetch('SELECT * FROM webhook_endpoints WHERE id = ?', [$id]);
    }

    /**
     * List all endpoints, optionally filtered by team.
     */
    public static function listEndpoints(?int $teamId = null): array
    {
        if ($teamId !== null) {
            return DB::fetchAll(
                'SELECT we.*, u.name AS creator_name
                 FROM webhook_endpoints we
                 LEFT JOIN users u ON u.id = we.created_by
                 WHERE we.team_id = ?
                 ORDER BY we.created_at DESC',
                [$teamId]
            );
        }

        return DB::fetchAll(
            'SELECT we.*, u.name AS creator_name
             FROM webhook_endpoints we
             LEFT JOIN users u ON u.id = we.created_by
             ORDER BY we.created_at DESC'
        );
    }

    /**
     * Regenerate the secret for an endpoint.
     * Returns the new secret.
     */
    public static function regenerateSecret(int $endpointId): string
    {
        $secret = bin2hex(random_bytes(32));
        DB::query(
            'UPDATE webhook_endpoints SET secret = ?, updated_at = NOW() WHERE id = ?',
            [$secret, $endpointId]
        );
        return $secret;
    }

    // ── Event Enqueuing ─────────────────────────────────────────────

    /**
     * Enqueue webhook deliveries for an event.
     * Called from EventService or controllers after write operations.
     *
     * @param string   $eventName  e.g. 'task.created'
     * @param array    $payload    The full webhook payload
     * @param int|null $teamId     Team context (null = global)
     */
    public static function enqueue(string $eventName, array $payload, ?int $teamId = null): void
    {
        try {
            // Find all active endpoints subscribed to this event
            $endpoints = self::getSubscribedEndpoints($eventName, $teamId);

            foreach ($endpoints as $endpoint) {
                $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                DB::query(
                    'INSERT INTO webhook_outbox
                        (webhook_endpoint_id, event_name, payload_json, status, attempts, next_attempt_at, created_at)
                     VALUES (?, ?, ?, ?, 0, NOW(), NOW())',
                    [(int) $endpoint['id'], $eventName, $payloadJson, 'pending']
                );
            }
        } catch (\Throwable $e) {
            Logger::error('WebhookService::enqueue failed', [
                'event' => $eventName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build a standard webhook payload.
     */
    public static function buildPayload(
        string $eventName,
        string $entityType,
        int $entityId,
        int $actorUserId,
        ?int $teamId,
        array $data
    ): array {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        $actor = User::findById($actorUserId);

        $entityUrl = $baseUrl . '/?r=';
        if ($entityType === 'task') {
            $entityUrl .= 'task_view&id=' . $entityId;
        } elseif ($entityType === 'page') {
            $entityUrl .= 'page_view&id=' . $entityId;
        } else {
            $entityUrl .= $entityType . '_view&id=' . $entityId;
        }

        return [
            'event'       => $eventName,
            'delivery_id' => 0, // Will be set to outbox.id on delivery
            'occurred_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'actor'       => [
                'id'   => $actorUserId,
                'name' => $actor['name'] ?? 'Unknown',
            ],
            'team_id'     => $teamId,
            'entity'      => [
                'type' => $entityType,
                'id'   => $entityId,
                'url'  => $entityUrl,
            ],
            'data'        => $data,
        ];
    }

    /**
     * Get all active endpoints subscribed to a given event.
     */
    private static function getSubscribedEndpoints(string $eventName, ?int $teamId): array
    {
        $endpoints = DB::fetchAll(
            'SELECT * FROM webhook_endpoints WHERE is_active = 1'
        );

        $matched = [];
        foreach ($endpoints as $ep) {
            $subscribedEvents = array_map('trim', explode(',', $ep['events']));
            if (!in_array($eventName, $subscribedEvents, true)) {
                continue;
            }

            // Team scoping: global webhooks (team_id IS NULL) fire for all events.
            // Team-specific webhooks only fire for events in their team.
            $epTeamId = $ep['team_id'] !== null ? (int) $ep['team_id'] : null;
            if ($epTeamId !== null && $teamId !== null && $epTeamId !== $teamId) {
                continue;
            }

            $matched[] = $ep;
        }

        return $matched;
    }
}
