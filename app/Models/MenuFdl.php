<?php

namespace App\Models;

use App\Models\Sector;

class MenuFdl extends Sector
{
    protected $table = 'menu_fdl';

    public $timestamps = false;
    protected $guarded = [];
   
}