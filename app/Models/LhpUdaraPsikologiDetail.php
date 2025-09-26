<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpUdaraPsikologiDetail extends Sector
{
    protected $table = "lhp_udara_psikologi_detail";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpUdaraPsikologiHeader()
    {
        return $this->belongsTo(LhpUdaraPsikologiHeader::class, 'id', 'id_header');
    }
}
