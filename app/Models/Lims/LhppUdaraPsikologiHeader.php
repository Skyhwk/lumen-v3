<?php

namespace App\Models\Lims;

use App\Models\LhppUdaraPsikologiDetail;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhppUdaraPsikologiHeader extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhpp_udara_psikologi_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhppUdaraPsikologiDetail()
    {
        return $this->hasMany(LhppUdaraPsikologiDetail::class, 'id_header', 'id');
    }
}
