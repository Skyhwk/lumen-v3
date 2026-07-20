<?php

namespace App\Models\Lims;

use App\Models\Sector;

class DFUSKeterangan extends Sector
{
    protected $connection = 'lims';

    protected $table = 'dfus_keterangan';
    public $timestamps = false;


    protected $guarded = [];
}
