<?php

namespace App\Models;

use App\Models\Sector;

class AsetUsedHistories extends Sector
{
    protected $table = 'aset_used_histories';

    public $timestamps = false;

    public function aset()
    {
        return $this->belongsTo(DataAset::class, 'aset_id', 'id');
    }
}