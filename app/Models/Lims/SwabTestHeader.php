<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class SwabTestHeader extends Sector{
    protected $connection = 'lims';


    protected $table = 'swabtest_header';
    public $timestamps = false;

    protected $guarded = [];

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Lims\Ftc'::class, 'no_sample', 'no_sampel');
    }

    public function ws_value()
    {
        return $this->hasOne(WsValueUdara::class, 'id_swabtest_header', 'id');
    }
}