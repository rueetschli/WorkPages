<?php
/**
 * DigestService - Compiles and enqueues digest emails (AP15).
 *
 * Collects un-emailed notifications for users with digest settings
 * and creates email_outbox entries with a summary.
 *
 * Called via admin route or cron.
 */
class DigestService
{
    /**
     * Process digest for all users with the given mode.
     *
     * @param string $mode  'digest_daily' or 'digest_weekly'
     * @return array{users_processed: int, emails_enqueued: int}
     */
    public static function process(string $mode): array
    {
        if (!in_array($mode, ['digest_daily', 'digest_weekly'], true)) {
            return ['users_processed' => 0, 'emails_enqueued' => 0];
        }

        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        $period  = $mode === 'digest_daily' ? 'daily' : 'weekly';

        // Find users with this digest mode
        $users = DB::fetchAll(
            'SELECT ns.user_id, u.name, u.email, ns.email_address_override
             FROM notification_settings ns
             JOIN users u ON u.id = ns.user_id AND u.is_active = 1
             WHERE ns.email_enabled = 1 AND ns.email_mode = ?',
            [$mode]
        );

        $processed = 0;
        $enqueued  = 0;

        foreach ($users as $userRow) {
            $userId = (int) $userRow['user_id'];
            $notifications = Notification::getUnemailed($userId);

            if (empty($notifications)) {
                continue;
            }

            $processed++;

            $toEmail = !empty($userRow['email_address_override'])
                ? $userRow['email_address_override']
                : $userRow['email'];

            if (empty($toEmail)) {
                continue;
            }

            try {
                $emailData = EmailService::renderDigestEmail(
                    $notifications,
                    $userRow,
                    $baseUrl,
                    $period
                );

                EmailOutbox::enqueue([
                    'user_id'         => $userId,
                    'notification_id' => null,
                    'to_email'        => $toEmail,
                    'subject'         => $emailData['subject'],
                    'body_html'       => $emailData['body_html'],
                    'body_text'       => $emailData['body_text'],
                    'send_after'      => date('Y-m-d H:i:s'),
                ]);

                // Mark all these notifications as emailed
                $notifIds = array_map(fn($n) => (int) $n['id'], $notifications);
                Notification::markEmailed($notifIds);

                $enqueued++;
            } catch (\Throwable $e) {
                Logger::error('Digest email failed', [
                    'user_id' => $userId,
                    'mode'    => $mode,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return ['users_processed' => $processed, 'emails_enqueued' => $enqueued];
    }
}
