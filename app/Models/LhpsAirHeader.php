<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsAirHeader extends Sector
{
    protected $table = "lhps_air_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsAirDetail()
    {
        return $this->hasMany(LhpsAirDetail::class, 'id_header', 'id');
    }

    public function lhpsAirCustom()
    {
        return $this->hasMany(LhpsAirCustom::class, 'id_header', 'id');
    }
    public function link ()
    {
        return $this->belongsTo('App\Models\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_air');
    }
    public function detail()
    {
        return $this->hasMany(LhpsAirDetail::class, 'id_header', 'id');
    }
}
