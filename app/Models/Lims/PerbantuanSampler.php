<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PerbantuanSampler extends Sector
{
    protected $connection = 'lims';
    protected $table = "perbantuan_sampler";
    public $timestamps = false;

    protected $guarded = [];

    public function users() {
        return $this->belongsTo('App\Models\Lims\MasterKaryawan', 'user_id', 'user_id');
    }
}
