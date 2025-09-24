<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganPsikologi extends Sector
{
    protected $table = "data_lapangan_psikologi";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }

    public function header()
    {
        return $this->belongsTo(PsikologiHeader::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }
}