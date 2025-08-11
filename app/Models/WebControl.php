<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WebControl extends Sector
{
    protected $connection = "company_profile";

    protected $table = "web_controls";
    public $timestamps = false;

    protected $guarded = [];
}
