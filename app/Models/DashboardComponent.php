<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DashboardComponent extends Sector
{
    protected $table = "dashboard_component";
    // public $timestamps = false;
    protected $guarded = [];

    // public function detail(){
    //     return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')
    //     ->where('is_active', true);
    // }
    
}