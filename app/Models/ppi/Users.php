<?php

namespace App\Models\ppi;

use App\Models\Sector;

class Users extends Sector
{
    protected $connection = 'portal_customer';
    protected $table = 'users';
    protected $guarded = ['id'];

    public $timestamps = false;
}
