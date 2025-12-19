<?php

namespace App\Models;

use App\Models\Sector;

use Illuminate\Database\Eloquent\SoftDeletes;

class MutasiFeeSales extends Sector
{
    use SoftDeletes;

    protected $guarded = ['id'];
}
