<?php
/**
 * WebhookDeliveryService - Sends queued webhooks and handles retry/dead letter (AP19).
 *
 * Uses cURL for HTTP delivery. Falls back to stream_context if cURL unavailable.
 * Retry policy: exponential backoff (1m, 5m, 30m, 2h, 12h). Max 10 attempts.
 */
class WebhookDeliveryService
{
    /** Maximum delivery attempts before marking as dead. */
    private const MAX_ATTEMPTS = 10;

    /** Retry intervals in seconds (index = attempt number starting from 1). */
    private const RETRY_INTERVALS = [
        1  => 60,       // 1 minute
        2  => 300,      // 5 minutes
        3  => 1800,     // 30 minutes
        4  => 7200,     // 2 hours
        5  => 43200,    // 12 hours
        6  => 43200,
        7  => 43200,
        8  => 43200,
        9  => 43200,
        10 => 43200,
    ];

    /** Connect timeout in seconds. */
    private const CONNECT_TIMEOUT = 5;

    /** Total request timeout in seconds. */
    private const TOTAL_TIMEOUT = 10;

    /**
     * Process pending webhook deliveries.
     * Call from admin UI or pseudo-cron.
     *
     * @param int $batchSize Maximum entries to process per call
     * @return array{sent: int, failed: int, dead: int}
     */
    public static function processPending(int $batchSize = 50): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'dead' => 0];

        $entries = DB::fetchAll(
            'SELECT wo.*, we.url, we.secret
             FROM webhook_outbox wo
             INNER JOIN webhook_endpoints we ON we.id = wo.webhook_endpoint_id
             WHERE wo.status IN (?, ?) AND wo.next_attempt_at <= NOW() AND we.is_active = 1
             ORDER BY wo.next_attempt_at ASC
             LIMIT ' . (int) $batchSize,
            ['pending', 'failed']
        );

        foreach ($entries as $entry) {
            $result = self::deliver($entry);

            if ($result['success']) {
                self::markSent((int) $entry['id']);
                $stats['sent']++;
            } else {
                $attempts = (int) $entry['attempts'] + 1;
                if ($attempts >= self::MAX_ATTEMPTS) {
                    self::markDead((int) $entry['id'], $result['error'], $result['http_status']);
                    $stats['dead']++;
                } else {
                    self::markFailed((int) $entry['id'], $attempts, $result['error'], $result['http_status']);
                    $stats['failed']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Deliver a single webhook entry.
     *
     * @return array{success: bool, http_status: int|null, error: string|null}
     */
    private static function deliver(array $entry): array
    {
        $payload = $entry['payload_json'];
        $url = $entry['url'];
        $secret = $entry['secret'];
        $outboxId = (int) $entry['id'];
        $eventName = $entry['event_name'];

        // Update delivery_id in payload
        $payloadData = json_decode($payload, true);
        if (is_array($payloadData)) {
            $payloadData['delivery_id'] = $outboxId;
            $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Compute HMAC signature
        $signature = hash_hmac('sha256', $payload, $secret);

        $headers = [
            'Content-Type: application/json',
            'X-WorkPages-Event: ' . $eventName,
            'X-WorkPages-Delivery: ' . $outboxId,
            'X-WorkPages-Signature: ' . $signature,
            'User-Agent: WorkPages-Webhook/1.0',
        ];

        if (function_exists('curl_init')) {
            return self::deliverWithCurl($url, $payload, $headers);
        }

        return self::deliverWithStream($url, $payload, $headers);
    }

    /**
     * Deliver using cURL.
     */
    private static function deliverWithCurl(string $url, string $payload, array $headers): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TOTAL_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            return ['success' => false, 'http_status' => null, 'error' => 'cURL: ' . $error];
        }

        // 2xx = success
        if ($httpStatus >= 200 && $httpStatus < 300) {
            return ['success' => true, 'http_status' => $httpStatus, 'error' => null];
        }

        return [
            'success'     => false,
            'http_status' => $httpStatus,
            'error'       => 'HTTP ' . $httpStatus,
        ];
    }

    /**
     * Deliver using stream_context (fallback).
     */
    private static function deliverWithStream(string $url, string $payload, array $headers): array
    {
        $headerStr = implode("\r\n", $headers);

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => $headerStr,
                'content'       => $payload,
                'timeout'       => self::TOTAL_TIMEOUT,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return ['success' => false, 'http_status' => null, 'error' => 'stream_context: request failed'];
        }

        // Parse HTTP status from response headers
        $httpStatus = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d{3})/', $h, $m)) {
                    $httpStatus = (int) $m[1];
                }
            }
        }

        if ($httpStatus >= 200 && $httpStatus < 300) {
            return ['success' => true, 'http_status' => $httpStatus, 'error' => null];
        }

        return [
            'success'     => false,
            'http_status' => $httpStatus,
            'error'       => 'HTTP ' . $httpStatus,
        ];
    }

    // ── Status updates ──────────────────────────────────────────────

    private static function markSent(int $outboxId): void
    {
        DB::query(
            'UPDATE webhook_outbox
             SET status = ?, attempts = attempts + 1, sent_at = NOW(), last_error = NULL
             WHERE id = ?',
            ['sent', $outboxId]
        );
    }

    private static function markFailed(int $outboxId, int $attempts, ?string $error, ?int $httpStatus): void
    {
        $interval = self::RETRY_INTERVALS[$attempts] ?? 43200;

        DB::query(
            'UPDATE webhook_outbox
             SET status = ?, attempts = ?, last_error = ?, last_http_status = ?,
                 next_attempt_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
             WHERE id = ?',
            ['failed', $attempts, $error, $httpStatus, $interval, $outboxId]
        );
    }

    private static function markDead(int $outboxId, ?string $error, ?int $httpStatus): void
    {
        DB::query(
            'UPDATE webhook_outbox
             SET status = ?, attempts = attempts + 1, last_error = ?, last_http_status = ?
             WHERE id = ?',
            ['dead', $error, $httpStatus, $outboxId]
        );
    }

    // ── Admin queries ───────────────────────────────────────────────

    /**
     * List outbox entries with optional status filter.
     */
    public static function listOutbox(?string $status = null, int $limit = 100): array
    {
        if ($status !== null) {
            return DB::fetchAll(
                'SELECT wo.*, we.name AS endpoint_name, we.url AS endpoint_url
                 FROM webhook_outbox wo
                 INNER JOIN webhook_endpoints we ON we.id = wo.webhook_endpoint_id
                 WHERE wo.status = ?
                 ORDER BY wo.created_at DESC
                 LIMIT ' . (int) $limit,
                [$status]
            );
        }

        return DB::fetchAll(
            'SELECT wo.*, we.name AS endpoint_name, we.url AS endpoint_url
             FROM webhook_outbox wo
             INNER JOIN webhook_endpoints we ON we.id = wo.webhook_endpoint_id
             ORDER BY wo.created_at DESC
             LIMIT ' . (int) $limit
        );
    }

    /**
     * Count outbox entries by status.
     */
    public static function countByStatus(): array
    {
        $rows = DB::fetchAll(
            'SELECT status, COUNT(*) AS cnt FROM webhook_outbox GROUP BY status'
        );

        $counts = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'dead' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }
        return $counts;
    }

    /**
     * Retry a dead entry (reset to pending).
     */
    public static function retryEntry(int $outboxId): bool
    {
        $entry = DB::fetch(
            'SELECT id FROM webhook_outbox WHERE id = ? AND status = ?',
            [$outboxId, 'dead']
        );

        if (!$entry) {
            return false;
        }

        DB::query(
            'UPDATE webhook_outbox SET status = ?, attempts = 0, next_attempt_at = NOW(), last_error = NULL WHERE id = ?',
            ['pending', $outboxId]
        );

        return true;
    }

    /**
     * Delete old sent entries (cleanup).
     */
    public static function cleanupSent(int $olderThanDays = 30): int
    {
        $stmt = DB::query(
            'DELETE FROM webhook_outbox WHERE status = ? AND sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
            ['sent', $olderThanDays]
        );
        return $stmt->rowCount();
    }
}
