<?php
/**
 * Simple file-based error logging. Errors are logged to LOG_PATH; users see generic messages.
 */
function log_error(string $message, array $context = []): void {
    $path = defined('LOG_PATH') ? LOG_PATH : (__DIR__ . '/../logs/app.log');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = empty($context) ? '' : ' ' . json_encode($context);
    $line = "[{$timestamp}] {$message}{$contextStr}" . PHP_EOL;
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}
