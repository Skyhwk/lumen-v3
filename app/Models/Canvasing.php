<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Canvasing extends Sector
{
    protected $table = "canvasing";
    public $timestamps = false;

    protected $guarded = [];
}