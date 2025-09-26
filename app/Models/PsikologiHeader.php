<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PsikologiHeader extends Sector{

    protected $table = 'psikologi_header';
    public $timestamps = false;

    public function data_lapangan() {
        return $this->belongsTo('App\Models\DataLapanganPsikologi', 'no_sampel', 'no_sampel');
    }

    public function order_detail() {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')->where('is_active', true);
    }
}

