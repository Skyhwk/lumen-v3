<?php

namespace App\Services\QuotationGenerate;

class QuotationAutoQtJobState
{
    public static function path(): string
    {
        return storage_path('app/quotation_auto_qt_job.json');
    }

    public static function write(string $month, bool $test = false, array $extra = []): void
    {
        $dir = dirname(self::path());
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Gagal membuat folder storage untuk job generateAutoQt.');
        }

        $ok = file_put_contents(self::path(), json_encode([
            'month' => $month,
            'test' => $test,
            'email' => trim((string) ($extra['email'] ?? '')) ?: null,
            'limit' => array_key_exists('limit', $extra) && $extra['limit'] !== null ? (int) $extra['limit'] : null,
            'written_at' => date('c'),
        ], JSON_UNESCAPED_UNICODE));

        if ($ok === false) {
            throw new \RuntimeException('Gagal menulis job generateAutoQt.');
        }
    }

    /**
     * @return array{month:string,test:bool,email:?string,limit:?int}
     */
    public static function read(): array
    {
        $path = self::path();
        if (!is_readable($path)) {
            throw new \RuntimeException('Job generateAutoQt tidak ditemukan. Jalankan ulang dari artisan generateAutoQt.');
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload) || empty($payload['month'])) {
            throw new \RuntimeException('Job generateAutoQt rusak. Jalankan ulang dari artisan generateAutoQt.');
        }

        return [
            'month' => (string) $payload['month'],
            'test' => !empty($payload['test']),
            'email' => isset($payload['email']) && trim((string) $payload['email']) !== ''
                ? trim((string) $payload['email'])
                : null,
            'limit' => isset($payload['limit']) && $payload['limit'] !== null && $payload['limit'] !== ''
                ? (int) $payload['limit']
                : null,
        ];
    }

    public static function clear(): void
    {
        $path = self::path();
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
