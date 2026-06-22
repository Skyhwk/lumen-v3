<?php

namespace App\Models;

use App\Models\Sector;

class DashboardUserOrder extends Sector
{
    protected $table = "dashboard_user_orders";
    protected $guarded = [];
    protected $casts = [
        'dashboard_order' => 'array',
    ];
}
