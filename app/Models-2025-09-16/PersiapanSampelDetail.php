<?php

namespace App\Models;

use App\Models\Sector;

class PersiapanSampelDetail extends Sector
{
    protected $table = 'persiapan_sampel_detail';

    public function psHeader()
    {
        return $this->belongsTo(PersiapanSampelHeader::class, 'id_persiapan_sampel_header')->with('orderHeader');
    }
}
