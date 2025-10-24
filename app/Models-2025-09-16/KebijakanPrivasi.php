<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class KebijakanPrivasi extends Sector
{
    protected $connection = "company_profile";
    protected $table = "kebijakan_privasi";
    public $timestamps = false;
    protected $guarded = [];

}
