<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class News extends Sector
{
    protected $connection = "company_profile";
    protected $table = "news";
    public $timestamps = false;
    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(CategoryNews::class, "category_news_id");
    }
}
