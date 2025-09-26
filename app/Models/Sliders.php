<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Sliders extends Sector
{
    protected $connection = "company_profile";
    protected $table = "sliders";
    public $timestamps = false;
    protected $guarded = [];
}
