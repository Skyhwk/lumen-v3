<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class NeedContacted extends Sector
{
    protected $table = "need_contacted";
    public $timestamps = false;
    protected $guarded = [];

}
