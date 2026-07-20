<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Sector;

class CoverLhp extends Sector
{
    protected $connection = 'lims';

    use SoftDeletes;

    protected $table = "cover_lhp";
    protected $guarded = ['id'];
}
