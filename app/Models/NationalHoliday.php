<?php

namespace App\Models;

use App\Models\Sector;

class NationalHoliday extends Sector
{
    protected $guarded = ['id'];
    public $timestamps = false;
}
