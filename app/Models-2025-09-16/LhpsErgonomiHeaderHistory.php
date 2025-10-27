<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsErgonomiHeaderHistory extends Sector
{
    protected $table = "lhps_ergonomi_header_history";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsEmisiDetail()
    {
        return $this->hasMany(LhpsErgonomiDetail::class, 'id_header', 'id');
    }

    public function link()
    {
        return $this->belongsTo('App\Models\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_udara');
    }
}
