<?php

namespace App\Models\Lims;

use App\Models\Sector;

use Illuminate\Database\Eloquent\SoftDeletes;

class FollowupBilling extends Sector
{
    protected $connection = 'lims';

    use SoftDeletes;

    protected $guarded = ["id"];
}
