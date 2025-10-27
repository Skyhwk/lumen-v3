<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PencahayaanHeader extends Sector{

    protected $table = 'pencahayaan_header';
    public $timestamps = false;

    protected $guarded = [];

    public function data_lapangan() {
        return $this->belongsTo('App\Models\DataLapanganCahaya', 'no_sampel', 'no_sampel');
    }
}