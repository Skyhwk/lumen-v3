<?php

namespace App\Models;

use App\Models\Sector;

class LimitWithdraw extends Sector
{
    protected $table = "limit_withdraw";
    protected $guarded = ['id'];

    public $timestamps = false;

}
