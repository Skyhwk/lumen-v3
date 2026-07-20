<?php

namespace App\Models\Lims;

use App\Models\Sector;

class FtcT extends Sector
{
    protected $connection = 'lims';

    protected $table = 't_ftc_t';
    public $timestamps = false;
    protected $guarded = [];
}
