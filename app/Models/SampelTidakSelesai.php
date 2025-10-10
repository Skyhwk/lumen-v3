<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\MasterCabang;

class SampelTidakSelesai extends Sector
{
    protected $table = "sampel_tidak_selesai";
    public $timestamps = false;
    protected $guarded = [];

    // public function cabang() {
    //     return $this->belongsTo(MasterCabang::class, 'id_cabang', 'id');
    // }
}
