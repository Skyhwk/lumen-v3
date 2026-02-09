<?php

namespace App\Models;

use App\Models\Sector;

class MutasiFeeSales extends Sector
{
    protected $guarded = ['id'];
    public $timestamp = false;
}
