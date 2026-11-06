<?php

namespace App\Services\QuotationGenerate;

class QuotationAutoQtJobState
{
    public const PHASE_BUILD = 'build';
    public const PHASE_RENDER = 'render';
    public const PHASE_EMAIL = 'email';
    public const PHASE_ALL = 'all';

    public static function path(): string
    {
        return storage_path('app/quotation_auto_qt_job.json');
    }

    public static function write(array $payload): void
    {
        $dir = dirname(self::path());
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Gagal membuat folder storage untuk job auto QT.');
        }

        $phase = (string) ($payload['phase'] ?? self::PHASE_BUILD);
        if (!in_array($phase, [self::PHASE_BUILD, self::PHASE_RENDER, self::PHASE_EMAIL, self::PHASE_ALL], true)) {
            throw new \InvalidArgumentException('Phase auto QT tidak dikenali: ' . $phase);
        }

        $ok = file_put_contents(self::path(), json_encode([
            'phase' => $phase,
            'month' => isset($payload['month']) && trim((string) $payload['month']) !== ''
                ? trim((string) $payload['month'])
                : null,
            'promo_id' => isset($payload['promo_id']) && $payload['promo_id'] !== null && $payload['promo_id'] !== ''
                ? (int) $payload['promo_id']
                : null,
            'test' => !empty($payload['test']),
            'email' => isset($payload['email']) && trim((string) $payload['email']) !== ''
                ? trim((string) $payload['email'])
                : null,
            'limit' => array_key_exists('limit', $payload) && $payload['limit'] !== null && $payload['limit'] !== ''
                ? (int) $payload['limit']
                : null,
            'tanggal_penawaran' => isset($payload['tanggal_penawaran']) && trim((string) $payload['tanggal_penawaran']) !== ''
                ? trim((string) $payload['tanggal_penawaran'])
                : null,
            'written_at' => date('c'),
        ], JSON_UNESCAPED_UNICODE));

        if ($ok === false) {
            throw new \RuntimeException('Gagal menulis job auto QT.');
        }
    }

    /**
     * @return array{phase:string,month:?string,promo_id:?int,test:bool,email:?string,limit:?int,tanggal_penawaran:?string}
     */
    public static function read(): array
    {
        $path = self::path();
        if (!is_readable($path)) {
            throw new \RuntimeException('Job auto QT tidak ditemukan. Jalankan ulang dari artisan.');
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Job auto QT rusak. Jalankan ulang dari artisan.');
        }

        $phase = (string) ($payload['phase'] ?? self::PHASE_BUILD);
        if (($phase === self::PHASE_BUILD || $phase === self::PHASE_ALL) && empty($payload['month'])) {
            throw new \RuntimeException('Job generateAutoQt rusak: bulan kosong.');
        }
        if (($phase === self::PHASE_RENDER || $phase === self::PHASE_EMAIL) && empty($payload['promo_id'])) {
            throw new \RuntimeException('Job auto QT rusak: promo_id kosong.');
        }

        return [
            'phase' => $phase,
            'month' => isset($payload['month']) && trim((string) $payload['month']) !== ''
                ? trim((string) $payload['month'])
                : null,
            'promo_id' => isset($payload['promo_id']) && $payload['promo_id'] !== null && $payload['promo_id'] !== ''
                ? (int) $payload['promo_id']
                : null,
            'test' => !empty($payload['test']),
            'email' => isset($payload['email']) && trim((string) $payload['email']) !== ''
                ? trim((string) $payload['email'])
                : null,
            'limit' => isset($payload['limit']) && $payload['limit'] !== null && $payload['limit'] !== ''
                ? (int) $payload['limit']
                : null,
            'tanggal_penawaran' => isset($payload['tanggal_penawaran']) && trim((string) $payload['tanggal_penawaran']) !== ''
                ? trim((string) $payload['tanggal_penawaran'])
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
