<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class SetAksesDashboard extends Sector
{
    protected $table = "set_akses_dashboard";
    // public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'user_list' => 'array',
        'user_visibility' => 'array',
    ];

    // public function detail(){
    //     return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')
    //     ->where('is_active', true);
    // }
    
}
