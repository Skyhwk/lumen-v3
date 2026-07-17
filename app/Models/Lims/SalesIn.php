<?php

namespace App\Models\Lims;

use App\Models\Sector;

class SalesIn extends Sector
{
    protected $connection = 'lims';

    protected $table = 'sales_in';
    public $timestamps = false;

    protected $guarded = [];

    public function detail()
    {
        return $this->hasMany(SalesInDetail::class, 'id_header', 'id')->where('is_active', true)->orderByDesc('id');
    }

}
