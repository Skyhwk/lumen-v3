<?php

namespace App\Models;

use App\Models\Sector;

class ScanBotol extends Sector
{
    protected $table = 'scan_botol';

    public $timestamps = false;
    protected $guarded = [];
   
}