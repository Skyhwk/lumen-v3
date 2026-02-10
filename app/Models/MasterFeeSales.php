<?php

namespace App\Models;

use App\Models\Sector;

class MasterFeeSales extends Sector
{
    protected $guarded = ['id'];
    public $timestamp = false;
}
