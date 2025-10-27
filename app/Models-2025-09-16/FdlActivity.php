<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class FdlActivity extends Sector {
    protected $table = "fdl_activity";
    public $timestamps = false;

    protected $guarded = [];

    public function user() {
        return $this->belongsTo(MasterKaryawan::class, 'user_id', 'id');
    }   
}