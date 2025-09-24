<?php

namespace App\Models;

use App\Models\Sector;

class SalesIn extends Sector
{
    protected $table = 'sales_in';
    public $timestamps = false;

    protected $guarded = [];

    public function detail()
    {
        return $this->hasMany(SalesInDetail::class, 'id_header', 'id');
    }

}
