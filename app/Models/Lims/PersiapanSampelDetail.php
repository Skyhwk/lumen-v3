<?php

namespace App\Models\Lims;

use App\Models\Sector;

class PersiapanSampelDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = 'persiapan_sampel_detail';

    public function psHeader()
    {
        return $this->belongsTo(PersiapanSampelHeader::class, 'id_persiapan_sampel_header')->with('orderHeader');
    }
}
