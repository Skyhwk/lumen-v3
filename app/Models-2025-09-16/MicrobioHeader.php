<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MicrobioHeader extends Sector
{

    protected $table = 'microbio_header';
    public $timestamps = false;

    public function ws_value()
    {
        return $this->belongsTo('App\Models\WsValueUdara', 'id', 'id_microbiologi_header');
    }

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }
    public function order_detail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')->where('is_active', true);
    }
}
