<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsIklimHeader extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_iklim_header";
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'metode_sampling' => 'array',
    ];

    public function lhpsIklimDetail()
    {
        return $this->hasMany(LhpsIklimDetail::class, 'id_header', 'id');
    }

    public function link ()
    {
        return $this->belongsTo('App\Models\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_iklim');
    }
}