<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Sector;

class CoverLhp extends Sector
{
    use SoftDeletes;

    protected $table = "cover_lhp";
    protected $guarded = ['id'];
}
