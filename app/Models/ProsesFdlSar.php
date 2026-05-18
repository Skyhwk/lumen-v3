<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class ProsesFdlSar extends Sector
{
    protected $table = "proses_fdl_sar";
    public $timestamps = false;

    protected $guarded = [];

    public function cekData(){
        return $this->belongsTo(SarHeader::class, 'no_order', 'no_order')
        ->where('is_active', true);
    }
}