<?php

namespace App\Models;

use App\Models\Sector;

class DFUSKeterangan extends Sector
{
    protected $table = 'dfus_keterangan';
    public $timestamps = false;


    protected $guarded = [];
}
