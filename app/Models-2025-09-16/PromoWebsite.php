<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PromoWebsite extends Sector
{
    protected $connection = "company_profile";
    protected $table = "promo";
    public $timestamps = false;
    protected $guarded = [];

}
