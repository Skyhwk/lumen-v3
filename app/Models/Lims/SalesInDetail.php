<?php

namespace App\Models\Lims;

use App\Models\Sector;

class SalesInDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = 'sales_in_detail';
    public $timestamps = false;

    protected $guarded = [];

    public function header()
    {
        return $this->belongsTo(SalesIn::class, 'id_header', 'id');
    }
}
