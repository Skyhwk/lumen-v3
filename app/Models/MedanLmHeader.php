<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MedanLmHeader extends Sector
{
 
    protected $table = 'medanlm_header';
    public $timestamps = false;

    public function ws_udara()
    {
        return $this->belongsTo('App\Models\WsValueUdara', 'no_sampel', 'no_sampel');
    }
    public function datalapangan()
    {
        return $this->belongsTo('App\Models\DataLapanganSinarUV', 'no_sampel', 'no_sampel');
    }

}