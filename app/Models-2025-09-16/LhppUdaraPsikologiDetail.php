<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhppUdaraPsikologiDetail extends Sector
{
    protected $table = "lhp_udara_psikologi_detail";
    public $timestamps = false;

    protected $guarded = [];

    public function lhppUdaraPsikologiHeader()
    {
        return $this->belongsTo(LhppUdaraPsikologiHeader::class, 'id', 'id_header');
    }
}
