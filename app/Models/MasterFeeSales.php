<?php

namespace App\Models;

use App\Models\Sector;

use Illuminate\Database\Eloquent\SoftDeletes;

class MasterFeeSales extends Sector
{
    use SoftDeletes;

    protected $guarded = ['id'];
}
