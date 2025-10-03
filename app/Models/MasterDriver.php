<?php

namespace App\Models;

use App\Models\Sector;

class MasterDriver extends Sector
{
    protected $table = "master_driver";
    public $timestamps = false;
    protected $guarded = [];

}