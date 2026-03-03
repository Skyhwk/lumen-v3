<?php

namespace App\Models;

use App\Models\Sector;

class AsetTransferHistories extends Sector
{
    protected $table = 'aset_transfer_histories';

    public $timestamps = false;

    public function aset()
    {
        return $this->belongsTo(DataAset::class, 'aset_id', 'id');
    }
}