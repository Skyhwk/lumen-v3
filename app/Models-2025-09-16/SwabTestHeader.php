<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class SwabTestHeader extends Sector
{

    protected $table = 'swabtest_header';
    public $timestamps = false;

    protected $guarded = [];

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }
    public function ws_value()
    {
        return $this->belongsTo('App\Models\WsValueSwab', 'id', 'id_swab_header')->where('is_active', true);
    }
    public function order_detail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')->where('is_active', true);
    }
}
