<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsLingHeader extends Sector
{
    protected $table = "lhps_ling_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsLingDetail()
    {
        return $this->hasMany(LhpsLingDetail::class, 'id_header', 'id');
    }
    public function lhpsLingCustom()
    {
        return $this->hasMany(LhpsLingCustom::class, 'id_header', 'id');
    }
    public function link ()
    {
        return $this->belongsTo('App\Models\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_ambient');
    }public function detail()
    {
        return $this->hasMany(LhpsLingDetail::class, 'id_header', 'id');
    }

 
}