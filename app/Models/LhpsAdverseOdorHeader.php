<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsAdverseOdorHeader extends Sector
{
    protected $table = "lhps_adverse_odor_header";
    public $timestamps = false;

    protected $guarded = [];

    public function link()
    {
        return $this->belongsTo('App\Models\GenerateLink', 'id', 'id_quotation')
            ->where('quotation_status', 'draft_ambient');
    }

 
}