<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class ErgonomiHeader extends Sector
{

    protected $table = 'ergonomi_header';
    public $timestamps = false;

    protected $guarded = [];

    public function order_detail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')->where('is_active', true);
    }
    public function datalapangan()
    {
        return $this->belongsTo('App\Models\DataLapanganErgonomi', 'id_lapangan', 'id');
    }


}