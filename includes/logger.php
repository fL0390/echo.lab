<?php
function dlog(string $level, string $message, array $context = []): void {
    if (!defined('TESTING_MODE') || !TESTING_MODE) return;

    $logPath = __DIR__ . '/../data/logs.json';
    $dir = dirname($logPath);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $entries = [];
    if (file_exists($logPath)) {
        $entries = json_decode(file_get_contents($logPath), true) ?: [];
    }

    $entries[] = [
        'time'    => date('Y-m-d H:i:s'),
        'ms'      => round(microtime(true) * 1000) % 100000,
        'level'   => $level,
        'message' => $message,
        'context' => $context,
        'source'  => basename($_SERVER['PHP_SELF'] ?? 'cli'),
    ];

    if (count($entries) > 200) {
        $entries = array_slice($entries, -200);
    }

    file_put_contents($logPath, json_encode($entries, JSON_PRETTY_PRINT));
}
