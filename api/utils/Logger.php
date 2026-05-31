<?php
class Logger {
    public static function write(string $level, string $action, ?int $userId, array $context = []): void {
        $logDir  = __DIR__ . '/../../logs';
        $logFile = $logDir . '/app.log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $line = sprintf(
            "[%s] [%s] [%s] user_id=%s ip=%s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $action,
            $userId ?? 'null',
            $ip,
            json_encode($context, JSON_UNESCAPED_UNICODE)
        );
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
