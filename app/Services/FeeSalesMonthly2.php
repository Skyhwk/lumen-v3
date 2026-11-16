<?php

namespace App\Services;

/**
 * @deprecated Digabung ke `App\Console\Commands\CalculateFeeSales`. Legacy: app/Services/_legacy/FeeSalesMonthly2.php.bak
 */
class FeeSalesMonthly2
{
    public function run($period = null): void
    {
        throw new \RuntimeException('FeeSalesMonthly2 sudah diganti. Jalankan: php artisan calculatefee:run');
    }
}
