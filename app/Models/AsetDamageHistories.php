<?php

namespace App\Models;

use App\Models\Sector;

class AsetDamageHistories extends Sector
{
    protected $table = 'aset_damage_histories';

    public $timestamps = false;

    public function aset()
    {
        return $this->belongsTo(DataAset::class, 'aset_id', 'id');
    }
}