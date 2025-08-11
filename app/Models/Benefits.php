<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Benefits extends Sector
{
    protected $connection = "company_profile";
    protected $table = "benefits";
    public $timestamps = false;
    protected $guarded = [];
}
