<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PencahayaanHeader extends Sector{

    protected $table = 'pencahayaan_header';
    public $timestamps = false;

    protected $guarded = [];

    public function lapangan_cahaya() {
        return $this->belongsTo('App\Models\DataLapanganCahaya', 'no_sampel', 'no_sampel');
    }

    public function master_parameter() {
        return $this->belongsTo('App\Models\Parameter', 'parameter', 'nama_lab')->where('id_kategori', 4)->where('is_active', true);
    }
    public function data_lapangan() {
        return $this->belongsTo('App\Models\DataLapanganCahaya', 'no_sampel', 'no_sampel');
    }

    public function ws_udara() {
        return $this->belongsTo('App\Models\WsValueUdara', 'no_sampel', 'no_sampel');
    }

    public function orderDetail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel');
    }
}
