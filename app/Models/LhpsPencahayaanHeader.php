<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPencahayaanHeader extends Sector
{
    protected $table = "lhps_pencahayaan_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsPencahayaanDetail()
    {
        return $this->hasMany(LhpsPencahayaanDetail::class, 'id_header', 'id');
    }

    public function lhpsPencahayaanCustom()
    {
        return $this->hasMany(LhpsPencahayaanCustom::class, 'id_header', 'id');
    }

    public function link ()
    {
        return $this->belongsTo('App\Models\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_pencahayaan');
    }
}
