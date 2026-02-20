<?php

namespace App\Models;

use App\Models\Sector;

class AsetFixingHistories extends Sector
{
    protected $table = 'aset_fixing_histories';

    public $timestamps = false;

    public function aset()
    {
        return $this->belongsTo(DataAset::class, 'aset_id', 'id');
    }
}