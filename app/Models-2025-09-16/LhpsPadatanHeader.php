<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPadatanHeader extends Sector
{
    protected $table = "lhps_padatan_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsEmisiDetail()
    {
        return $this->hasMany(LhpsEmisiDetail::class, 'id_header', 'id');
    }

    public function link ()
    {
        return $this->belongsTo('App\Models\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_emisi');
    }
}
