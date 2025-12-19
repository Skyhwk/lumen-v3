<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PerbantuanSampler extends Sector
{
    protected $table = "perbantuan_sampler";
    public $timestamps = false;

    protected $guarded = [];

    public function users() {
        return $this->belongsTo('App\Models\MasterKaryawan', 'user_id', 'user_id');
    }
}