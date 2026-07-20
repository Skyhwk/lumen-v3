<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsHygieneSanitasiHeader extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_hygiene_sanitasi_header";
    public $timestamps = false;

    protected $guarded = [];

    // public function lhpsLingDetail()
    // {
    //     return $this->hasMany(LhpsLingDetail::class, 'id_header', 'id');
    // }
    // public function lhpsLingCustom()
    // {
    //     return $this->hasMany(LhpsLingCustom::class, 'id_header', 'id');
    // }
    public function link ()
    {
        return $this->belongsTo('App\Models\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_ambient');
    }

 
}