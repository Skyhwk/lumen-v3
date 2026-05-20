<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DashboardComponent extends Sector
{
    protected $table = "dashboard_component";
    protected $guarded = [];
}