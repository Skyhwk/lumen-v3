<?php

namespace App\Models\customer;

use App\Models\Sector;

class Menus extends Sector
{
    protected $connection = "portal_customer";
    protected $table = 'menus';
    protected $guarded = ['id'];
    
    public $timestamps = false;
}
