<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class CategoryNews extends Sector
{
    protected $connection = "company_profile";
    protected $table = "category_news";
    public $timestamps = false;
    protected $guarded = [];

}
