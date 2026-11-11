<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class WorkerFeeSales
{
    public static function run(): void
    {
        try {
            Artisan::call('calculatefee:run');
            Log::info('[WorkerFeeSales] ' . trim(Artisan::output()));
        } catch (\Throwable $th) {
            Log::error('[WorkerFeeSales] Error: ' . $th->getMessage());
        }
    }
}
