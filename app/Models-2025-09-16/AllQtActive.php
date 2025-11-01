<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class AllQtActive extends Sector
{
    protected $table = "all_qt_active";
    public $timestamps = false;
    protected $primaryKey = null;
    public $incrementing = false;

    protected $guarded = [];
}
