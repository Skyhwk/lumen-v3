<?php

namespace App\Models\Gd\Concerns;

trait UsesGdProduksiConnection
{
    public function getConnectionName()
    {
        return config('greatday.produksi_connection', env('DB_CONNECTION', 'mysql'));
    }
}
