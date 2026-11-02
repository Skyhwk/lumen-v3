<?php

namespace App\Models\ppi;

use App\Models\Sector;

class Menus extends Sector
{
    protected $connection = 'portal_customer';
    protected $table = 'menus';
    protected $guarded = ['id'];

    public $timestamps = false;
}
