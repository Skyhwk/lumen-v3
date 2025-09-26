<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsGetaranHeader extends Sector
{
    protected $table = "lhps_getaran_header";
    public $timestamps = false;

    protected $guarded = [];

    public function lhpsGetaranDetail()
    {
        return $this->hasMany(LhpsGetaranDetail::class, 'id_header', 'id');
    }
    public function lhpsGetaranCustom()
    {
        return $this->hasMany(LhpsGetaranCustom::class, 'id_header', 'id');
    }

    public function order_detail()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')->where('is_active',1);
    }

    public function link()
    {
        return $this->belongsTo('App\Models\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_lhp_getaran');
    }

 
}