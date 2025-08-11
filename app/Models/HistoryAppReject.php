<?php

namespace App\Models;

use App\Models\Sector;

class HistoryAppReject extends Sector
{
    protected $table = 'history_app_reject';
    public $timestamps = false;
    protected $guarded = [];
}
