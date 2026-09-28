<?php

namespace App\Models\Greatday\Concerns;

trait UsesGreatdayProduksiConnection
{
    public function getConnectionName()
    {
        return config('greatday.produksi_connection', config('database.default', 'mysql'));
    }
}
