<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class SinarUvHeader extends Sector
{

    protected $table = 'sinaruv_header';
    public $timestamps = false;

    protected $guarded = [];
    public function ws_udara()
    {
        return $this->belongsTo('App\Models\WsValueUdara', 'no_sampel', 'no_sampel');
    }
    public function datalapangan()
    {
        return $this->belongsTo('App\Models\DataLapanganSinarUV', 'no_sampel', 'no_sampel');
    }

}