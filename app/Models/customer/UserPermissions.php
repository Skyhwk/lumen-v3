<?php

namespace App\Models\customer;

use App\Models\Sector;

class UserPermissions extends Sector
{
    protected $connection = "portal_customer";
    protected $table = 'user_permissions';
    protected $guarded = ['id'];

    public $timestamps = false;
}
