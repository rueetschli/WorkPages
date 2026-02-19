<?php
/**
 * EmailService - Email rendering and sending (AP15).
 *
 * Supports PHP mail() and optionally SMTP.
 * Templates are inline (no external files) for shared-hosting simplicity.
 */
class EmailService
{
    /**
     * Send a single email from the outbox.
     * Returns true on success, string error message on failure.
     *
     * @return true|string
     */
    public static function send(array $outboxRow): true|string
    {
        $mode = $GLOBALS['config']['MAIL_MODE'] ?? 'mail';

        if ($mode === 'smtp') {
            return self::sendSmtp($outboxRow);
        }

        return self::sendMail($outboxRow);
    }

    /**
     * Process pending outbox entries (batch send).
     *
     * @return array{sent: int, failed: int}
     */
    public static function processPending(int $limit = 10): array
    {
        $pending = EmailOutbox::getPending($limit);
        $sent = 0;
        $failed = 0;

        foreach ($pending as $row) {
            $result = self::send($row);

            if ($result === true) {
                EmailOutbox::markSent((int) $row['id']);
                $sent++;
            } else {
                EmailOutbox::markFailed((int) $row['id'], $result);
                $failed++;
                Logger::error('Email send failed', [
                    'outbox_id' => $row['id'],
                    'to'        => $row['to_email'],
                    'error'     => $result,
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Render email content for a single notification.
     *
     * @return array{subject: string, body_html: string, body_text: string}
     */
    public static function renderNotificationEmail(array $notification, array $user, string $baseUrl): array
    {
        try {
            $appName = SystemSettingsService::companyName();
        } catch (Throwable $e) {
            $appName = $GLOBALS['config']['APP_NAME'] ?? 'WorkPages';
        }
        $title   = $notification['title'] ?? '';
        $body    = $notification['body'] ?? '';
        $url     = $notification['url'] ?? $baseUrl;

        $subject = '[' . $appName . '] ' . $title;

        $settingsUrl = $baseUrl . '/?r=settings_notifications';

        $bodyText = $title . "\n\n";
        if ($body !== '') {
            $bodyText .= $body . "\n\n";
        }
        $bodyText .= "Zum Objekt: " . $url . "\n\n";
        $bodyText .= "---\n";
        $bodyText .= "Benachrichtigungseinstellungen: " . $settingsUrl . "\n";

        $bodyHtml = self::renderHtmlTemplate([
            'app_name'     => htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'),
            'title'        => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            'body'         => htmlspecialchars($body, ENT_QUOTES, 'UTF-8'),
            'url'          => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            'settings_url' => htmlspecialchars($settingsUrl, ENT_QUOTES, 'UTF-8'),
            'user_name'    => htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8'),
        ]);

        return [
            'subject'   => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ];
    }

    /**
     * Render email content for a digest.
     *
     * @param array $notifications  Array of notification rows
     * @return array{subject: string, body_html: string, body_text: string}
     */
    public static function renderDigestEmail(array $notifications, array $user, string $baseUrl, string $period): array
    {
        try {
            $appName = SystemSettingsService::companyName();
        } catch (Throwable $e) {
            $appName = $GLOBALS['config']['APP_NAME'] ?? 'WorkPages';
        }
        $count = count($notifications);
        $periodLabel = $period === 'daily' ? 'Tages' : 'Wochen';

        $subject = '[' . $appName . '] ' . $periodLabel . '-Zusammenfassung: ' . $count . ' Benachrichtigung' . ($count !== 1 ? 'en' : '');

        $settingsUrl = $baseUrl . '/?r=settings_notifications';

        // Text version
        $bodyText = $periodLabel . "-Zusammenfassung\n";
        $bodyText .= $count . " neue Benachrichtigung" . ($count !== 1 ? "en" : "") . "\n\n";

        foreach ($notifications as $n) {
            $bodyText .= "- " . ($n['title'] ?? '') . "\n";
            if (!empty($n['body'])) {
                $bodyText .= "  " . $n['body'] . "\n";
            }
            $bodyText .= "  " . ($n['url'] ?? '') . "\n\n";
        }

        $bodyText .= "---\n";
        $bodyText .= "Benachrichtigungseinstellungen: " . $settingsUrl . "\n";

        // HTML version
        $itemsHtml = '';
        foreach ($notifications as $n) {
            $nTitle = htmlspecialchars($n['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $nBody  = htmlspecialchars($n['body'] ?? '', ENT_QUOTES, 'UTF-8');
            $nUrl   = htmlspecialchars($n['url'] ?? '', ENT_QUOTES, 'UTF-8');
            $itemsHtml .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee;">';
            $itemsHtml .= '<strong>' . $nTitle . '</strong>';
            if ($nBody !== '') {
                $itemsHtml .= '<br><span style="color:#666;">' . $nBody . '</span>';
            }
            $itemsHtml .= '<br><a href="' . $nUrl . '" style="color:#2563eb;">Anzeigen</a>';
            $itemsHtml .= '</td></tr>';
        }

        $bodyHtml = self::renderDigestHtmlTemplate([
            'app_name'     => htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'),
            'period_label' => $periodLabel,
            'count'        => $count,
            'items_html'   => $itemsHtml,
            'settings_url' => htmlspecialchars($settingsUrl, ENT_QUOTES, 'UTF-8'),
            'user_name'    => htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8'),
        ]);

        return [
            'subject'   => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ];
    }

    // ── Send methods ────────────────────────────────────────────────

    /**
     * Send via PHP mail().
     *
     * @return true|string
     */
    private static function sendMail(array $row): true|string
    {
        try {
            $appName = SystemSettingsService::companyName();
        } catch (Throwable $e) {
            $appName = $GLOBALS['config']['APP_NAME'] ?? 'WorkPages';
        }
        $fromEmail = $GLOBALS['config']['MAIL_FROM'] ?? ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromName  = $GLOBALS['config']['MAIL_FROM_NAME'] ?? $appName;

        $headers = [
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: WorkPages-AP15',
        ];

        $result = @mail(
            $row['to_email'],
            $row['subject'],
            $row['body_html'],
            implode("\r\n", $headers)
        );

        if ($result) {
            return true;
        }

        $lastError = error_get_last();
        return $lastError['message'] ?? 'mail() returned false';
    }

    /**
     * Send via SMTP (minimal fsockopen implementation).
     *
     * @return true|string
     */
    private static function sendSmtp(array $row): true|string
    {
        $host    = $GLOBALS['config']['SMTP_HOST'] ?? 'localhost';
        $port    = (int) ($GLOBALS['config']['SMTP_PORT'] ?? 587);
        $user    = $GLOBALS['config']['SMTP_USER'] ?? '';
        $pass    = $GLOBALS['config']['SMTP_PASS'] ?? '';
        $secure  = $GLOBALS['config']['SMTP_SECURE'] ?? 'tls';
        $from    = $GLOBALS['config']['MAIL_FROM'] ?? ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        try {
            $__smtpAppName = SystemSettingsService::companyName();
        } catch (Throwable $e) {
            $__smtpAppName = $GLOBALS['config']['APP_NAME'] ?? 'WorkPages';
        }
        $fromName = $GLOBALS['config']['MAIL_FROM_NAME'] ?? $__smtpAppName;

        $prefix = ($secure === 'ssl') ? 'ssl://' : '';
        $fp = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);

        if (!$fp) {
            return 'SMTP connect failed: ' . $errstr . ' (' . $errno . ')';
        }

        stream_set_timeout($fp, 10);

        $response = self::smtpRead($fp);
        if (strpos($response, '220') !== 0) {
            fclose($fp);
            return 'SMTP unexpected greeting: ' . $response;
        }

        // EHLO
        self::smtpWrite($fp, 'EHLO ' . gethostname());
        $response = self::smtpRead($fp);

        // STARTTLS if needed
        if ($secure === 'tls' && strpos($response, 'STARTTLS') !== false) {
            self::smtpWrite($fp, 'STARTTLS');
            $response = self::smtpRead($fp);
            if (strpos($response, '220') !== 0) {
                fclose($fp);
                return 'STARTTLS failed: ' . $response;
            }
            $cryptoResult = stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            if (!$cryptoResult) {
                fclose($fp);
                return 'TLS encryption failed';
            }
            self::smtpWrite($fp, 'EHLO ' . gethostname());
            self::smtpRead($fp);
        }

        // AUTH LOGIN
        if ($user !== '') {
            self::smtpWrite($fp, 'AUTH LOGIN');
            $response = self::smtpRead($fp);
            if (strpos($response, '334') !== 0) {
                fclose($fp);
                return 'AUTH LOGIN not supported: ' . $response;
            }

            self::smtpWrite($fp, base64_encode($user));
            $response = self::smtpRead($fp);
            if (strpos($response, '334') !== 0) {
                fclose($fp);
                return 'AUTH username rejected: ' . $response;
            }

            self::smtpWrite($fp, base64_encode($pass));
            $response = self::smtpRead($fp);
            if (strpos($response, '235') !== 0) {
                fclose($fp);
                return 'AUTH login failed: ' . $response;
            }
        }

        // MAIL FROM
        self::smtpWrite($fp, 'MAIL FROM:<' . $from . '>');
        $response = self::smtpRead($fp);
        if (strpos($response, '250') !== 0) {
            fclose($fp);
            return 'MAIL FROM rejected: ' . $response;
        }

        // RCPT TO
        self::smtpWrite($fp, 'RCPT TO:<' . $row['to_email'] . '>');
        $response = self::smtpRead($fp);
        if (strpos($response, '250') !== 0) {
            fclose($fp);
            return 'RCPT TO rejected: ' . $response;
        }

        // DATA
        self::smtpWrite($fp, 'DATA');
        $response = self::smtpRead($fp);
        if (strpos($response, '354') !== 0) {
            fclose($fp);
            return 'DATA rejected: ' . $response;
        }

        // Message content
        $message  = 'From: ' . $fromName . ' <' . $from . ">\r\n";
        $message .= 'To: ' . $row['to_email'] . "\r\n";
        $message .= 'Subject: =?UTF-8?B?' . base64_encode($row['subject']) . "?=\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "X-Mailer: WorkPages-AP15\r\n";
        $message .= "\r\n";
        $message .= $row['body_html'];
        $message .= "\r\n.\r\n";

        fwrite($fp, $message);
        $response = self::smtpRead($fp);
        if (strpos($response, '250') !== 0) {
            fclose($fp);
            return 'Message rejected: ' . $response;
        }

        self::smtpWrite($fp, 'QUIT');
        fclose($fp);

        return true;
    }

    private static function smtpWrite($fp, string $line): void
    {
        fwrite($fp, $line . "\r\n");
    }

    private static function smtpRead($fp): string
    {
        $response = '';
        while ($line = fgets($fp, 512)) {
            $response .= $line;
            // Multi-line responses: continue if char 4 is '-'
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }

    // ── HTML Templates ──────────────────────────────────────────────

    private static function renderHtmlTemplate(array $vars): string
    {
        return '<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;max-width:600px;">
  <tr><td style="background:#1e293b;padding:16px 24px;color:#fff;font-size:14px;font-weight:600;">' . $vars['app_name'] . '</td></tr>
  <tr><td style="padding:24px;">
    <p style="margin:0 0 8px;font-size:14px;color:#666;">Hallo ' . $vars['user_name'] . ',</p>
    <h2 style="margin:0 0 12px;font-size:18px;color:#1e293b;">' . $vars['title'] . '</h2>
    ' . ($vars['body'] !== '' ? '<p style="margin:0 0 16px;color:#475569;font-size:14px;">' . $vars['body'] . '</p>' : '') . '
    <p style="margin:16px 0 0;">
      <a href="' . $vars['url'] . '" style="display:inline-block;background:#2563eb;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:14px;">Anzeigen</a>
    </p>
  </td></tr>
  <tr><td style="padding:16px 24px;border-top:1px solid #e5e7eb;font-size:12px;color:#94a3b8;">
    <a href="' . $vars['settings_url'] . '" style="color:#64748b;">Benachrichtigungseinstellungen</a>
  </td></tr>
</table>
</td></tr></table>
</body></html>';
    }

    private static function renderDigestHtmlTemplate(array $vars): string
    {
        return '<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;max-width:600px;">
  <tr><td style="background:#1e293b;padding:16px 24px;color:#fff;font-size:14px;font-weight:600;">' . $vars['app_name'] . '</td></tr>
  <tr><td style="padding:24px;">
    <p style="margin:0 0 8px;font-size:14px;color:#666;">Hallo ' . $vars['user_name'] . ',</p>
    <h2 style="margin:0 0 16px;font-size:18px;color:#1e293b;">' . $vars['period_label'] . '-Zusammenfassung</h2>
    <p style="margin:0 0 16px;color:#475569;font-size:14px;">' . $vars['count'] . ' neue Benachrichtigung' . ($vars['count'] !== 1 ? 'en' : '') . '</p>
    <table width="100%" cellpadding="0" cellspacing="0">' . $vars['items_html'] . '</table>
  </td></tr>
  <tr><td style="padding:16px 24px;border-top:1px solid #e5e7eb;font-size:12px;color:#94a3b8;">
    <a href="' . $vars['settings_url'] . '" style="color:#64748b;">Benachrichtigungseinstellungen</a>
  </td></tr>
</table>
</td></tr></table>
</body></html>';
    }
}
