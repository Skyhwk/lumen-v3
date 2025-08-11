<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class CategoryService extends Sector
{
    protected $connection = "company_profile";

    protected $table = "category_services";
    public $timestamps = false;

    protected $guarded = [];

    public function lingkup()
    {
        return $this->belongsTo(LingkupService::class, "lingkup_service_id");
    }
}
