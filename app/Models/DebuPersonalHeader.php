<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DebuPersonalHeader extends Sector{

    protected $table = 'debu_personal_header';
    public $timestamps = false;

    protected $guarded = [];

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }

    public function ws_value()
    {
        return $this->belongsTo('App\Models\WsValueLingkungan', 'no_sampel', 'no_sampel');
    }

    public function order_detail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel');
    }
}