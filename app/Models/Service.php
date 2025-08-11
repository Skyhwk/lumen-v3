<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CategoryService;
use App\Models\LingkupService;
use App\Models\Sector;

class Service extends Sector
{
    protected $connection = "company_profile";

    protected $table = "services";
    public $timestamps = false;

    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(CategoryService::class, "category_id");
    }
}
