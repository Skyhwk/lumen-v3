<?php

/**
 * Test push FCM payroll (Attendance).
 *
 * Usage:
 *   php database/scripts/test_payroll_fcm_push.php [user_id] [fcm_token]
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->boot();

use App\Services\Greatday\FirebaseService;
use App\Support\Greatday\NotificationCopy;

$userId = isset($argv[1]) && is_numeric($argv[1]) ? (int) $argv[1] : 601;
$token = $argv[2] ?? '';

if ($token === '') {
    fwrite(STDERR, "FCM token wajib sebagai argumen ke-2.\n");
    exit(1);
}

$payload = NotificationCopy::payrollTransferCompleted('Oktober 2026');
$payload = array_map(function ($value) {
    return is_scalar($value) ? (string) $value : json_encode($value);
}, $payload);

try {
    $firebase = app(FirebaseService::class);
    $result = $firebase->sendNotification($token, $payload, $userId);
    echo "OK: push payroll test ke user_id={$userId}\n";
    var_export($result);
    echo "\n";
} catch (\Throwable $e) {
    fwrite(STDERR, 'Gagal: ' . $e->getMessage() . "\n");
    exit(1);
}
