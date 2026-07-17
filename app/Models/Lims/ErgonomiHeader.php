<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class ErgonomiHeader extends Sector{
    protected $connection = 'lims';


    protected $table = 'ergonomi_header';
    public $timestamps = false;

    protected $guarded = [];

    public function order_detail() {
        return $this->belongsTo('App\Models\Lims\OrderDetail', 'no_sampel', 'no_sampel')->with('orderHeader')->where('is_active', true);
    }

    public function datalapangan()
    {
        return $this->belongsTo('App\Models\Lims\DataLapanganErgonomi', 'id_lapangan', 'id')->with('detail');
    }

    public function ws_value_ergonomi()
    {
        return $this->belongsTo('App\Models\WsValueErgonomi', 'id_lapangan', 'id_data_lapangan');
    }

}