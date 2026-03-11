<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganPersonalHeader extends Sector
{
    protected $table = "lhps_kebisingan_personal_header";
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'metode_sampling' => 'array',
    ];

    public function lhpsKebisinganPersonalDetail()
    {
        return $this->hasMany(LhpsKebisinganPersonalDetail::class, 'id_header', 'id');
    }

    public function lhpsKebisinganPersonalCustom()
    {
        return $this->hasMany(LhpsKebisinganPersonalCustom::class, 'id_header', 'id');
    }

    public function link ()
    {
        return $this->belongsTo('App\Models\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_kebisingan');
    }

}