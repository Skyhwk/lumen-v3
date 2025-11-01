<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class GetaranHeader extends Sector
{
    protected $table = "getaran_header";
    public $timestamps = false;

    protected $guarded = [];
    public function lapangan_getaran() {
        return $this->belongsTo('App\Models\DataLapanganGetaran', 'no_sampel', 'no_sampel');
    }
    public function lapangan_getaran_personal() {
        return $this->belongsTo('App\Models\DataLapanganGetaranPersonal', 'no_sampel', 'no_sampel');
    }
}