<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class HistoryLevelSampler extends Sector
{
    protected $table = "history_level_sampler";
    public $timestamps = false;

    protected $guarded = [];

}
