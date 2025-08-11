<?php

namespace App\Models;

use App\Models\Sector;

class FtcT extends Sector
{
    protected $table = 't_ftc_t';
    public $timestamps = false;
    protected $guarded = [];
}
