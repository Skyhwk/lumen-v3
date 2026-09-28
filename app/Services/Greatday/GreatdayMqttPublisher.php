<?php

namespace App\Services\Greatday;

use Bluerhinos\phpMQTT;
use Illuminate\Support\Facades\Log;

/**
 * Realtime sync Greatday — topic terpisah dari portal (/notification/{id}).
 * Default: /greatday/sync/{employee_id}
 */
class GreatdayMqttPublisher
{
    public static function scopeFromUrl(?string $url): string
    {
        $path = trim((string) $url);
        if ($path === '' || $path === '/') {
            return 'home';
        }
        if (strpos($path, '/forms') === 0) {
            return 'forms';
        }
        if (strpos($path, '/notifications') === 0) {
            return 'notifications';
        }
        if (strpos($path, '/employees') === 0) {
            return 'employees';
        }
        if (strpos($path, 'attendance') !== false || strpos($path, '/miscellaneous') === 0) {
            return 'attendance';
        }
        if (strpos($path, '/announcements') === 0) {
            return 'announcements';
        }

        return 'general';
    }

    /**
     * @param  list<int|string>  $employeeIds
     * @param  array<string, mixed>  $notificationData
     */
    public static function publishSync(array $employeeIds, array $notificationData, string $event = 'data_changed'): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $employeeIds = array_values(array_unique(array_filter(array_map('intval', $employeeIds))));
        if ($employeeIds === []) {
            return;
        }

        $url = $notificationData['url'] ?? '/forms';
        $payload = json_encode([
            'app' => 'greatday',
            'scope' => self::scopeFromUrl($url),
            'event' => $event,
            'url' => $url,
            'ts' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE);

        $prefix = rtrim((string) env('GREATDAY_MQTT_TOPIC_PREFIX', '/greatday/sync'), '/');

        try {
            $host = env('MQTT_HOST');
            $port = (int) env('MQTT_PORT', 1883);
            [$username, $password] = self::resolveCredentials();
            $clientId = 'greatday_pub_' . uniqid('', true);

            $mqtt = new phpMQTT($host, $port, $clientId);
            if (!$mqtt->connect(true, null, $username, $password)) {
                Log::warning('Greatday MQTT: koneksi broker gagal');

                return;
            }

            foreach ($employeeIds as $id) {
                $mqtt->publish("{$prefix}/{$id}", $payload, 0);
            }

            $mqtt->close();
        } catch (\Throwable $e) {
            Log::warning('Greatday MQTT: publish gagal', ['message' => $e->getMessage()]);
        }
    }

    private static function isEnabled(): bool
    {
        if (!env('MQTT_HOST')) {
            return false;
        }

        $flag = env('GREATDAY_MQTT_ENABLED', true);

        return filter_var($flag, FILTER_VALIDATE_BOOL);
    }

    /**
     * Broker terbuka (default): connect tanpa user/pass.
     * Aktifkan auth hanya jika GREATDAY_MQTT_USE_AUTH=true dan username diisi.
     *
     * @return array{0: string, 1: string}
     */
    private static function resolveCredentials(): array
    {
        $useAuth = filter_var(env('GREATDAY_MQTT_USE_AUTH', false), FILTER_VALIDATE_BOOL);
        if (!$useAuth) {
            return ['', ''];
        }

        $username = trim((string) env('GREATDAY_MQTT_USERNAME', env('MQTT_USERNAME', '')));
        if ($username === '') {
            return ['', ''];
        }

        $password = (string) env('GREATDAY_MQTT_PASSWORD', env('MQTT_PASSWORD', ''));

        return [$username, $password];
    }
}
