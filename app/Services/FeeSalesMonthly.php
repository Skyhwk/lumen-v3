<?php

namespace App\Services;

/**
 * @deprecated Gunakan `php artisan calculatefee:run`. Implementasi lama: app/Services/_legacy/FeeSalesMonthly.php.bak
 */
class FeeSalesMonthly
{
    public function run($period = null): void
    {
        throw new \RuntimeException(
            'FeeSalesMonthly sudah diganti. Jalankan: php artisan calculatefee:run' . ($period ? ' ' . $period : '')
        );
    }
}
