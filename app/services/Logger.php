<?php
/**
 * Simple file-based logger. Writes to storage/logs/app.log.
 */
class Logger
{
    private static ?string $logFile = null;

    public static function init(string $logFile): void
    {
        self::$logFile = $logFile;
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        if (self::$logFile === null) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] [{$level}] {$message}";

        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line .= PHP_EOL;

        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        @file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
