<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsErgonomiHeader extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_ergonomi_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsErgonomiDetail()
    {
        return $this->hasMany(LhpsErgonomiDetail::class, 'id_header', 'id');
    }

    public function link()
    {
        return $this->belongsTo('App\Models\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_udara');
    }
}
