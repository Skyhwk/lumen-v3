<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiHeader extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_emisi_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsEmisiDetail()
    {
        return $this->hasMany(LhpsEmisiDetail::class, 'id_header', 'id');
    }

    public function lhpsEmisiCustom()
    {
        return $this->hasMany(LhpsEmisiCustom::class, 'id_header', 'id');
    }

    public function link ()
    {
        return $this->belongsTo('App\Models\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_emisi');
    }
}
