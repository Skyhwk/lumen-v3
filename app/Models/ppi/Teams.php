<?php

namespace App\Models\ppi;

use App\Models\Sector;

class Teams extends Sector
{
    protected $connection = 'portal_customer';
    protected $table = 'teams';
    protected $guarded = ['id'];

    public $timestamps = false;
}
