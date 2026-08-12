<?php

namespace App\Models;

use App\Models\Concerns\SyncsWsFinalApproval;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class SwabTestHeader extends Sector{
    use SyncsWsFinalApproval;

    protected $table = 'swabtest_header';
    public $timestamps = false;

    protected $guarded = [];

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }

    public function order_detail()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel');
    }

    public function detail_lapangan()
    {
        return $this->belongsTo(DataLapanganSwab::class, 'no_sampel', 'no_sampel');
    }
    
    public function ws_udara()
    {
        return $this->hasOne(WsValueUdara::class, 'id_swab_header', 'id');
    }

    public function createdByKaryawan()
    {
        return $this->belongsTo('App\Models\MasterKaryawan', 'created_by', 'nama_lengkap');
    }
}
