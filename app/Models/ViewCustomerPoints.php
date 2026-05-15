<?php

namespace App\Models;

use App\Models\Sector;

class ViewCustomerPoints extends Sector
{
    protected $connection = 'portal_customer';
    protected $table = 'view_customer_points';
    public $timestamps = false;
}