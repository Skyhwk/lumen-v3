<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LingkupService extends Sector
{
    protected $connection = "company_profile";

    protected $table = "lingkup_services";
    public $timestamps = false;

    protected $guarded = [];

    public function category()
    {
        return $this->hasMany(CategoryService::class, 'id', 'lingkup_service_id');
    }
}
