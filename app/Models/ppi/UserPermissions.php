<?php

namespace App\Models\ppi;

use App\Models\Sector;

class UserPermissions extends Sector
{
    protected $connection = 'portal_pelanggan';
    protected $table = 'user_permissions';
    protected $guarded = ['id'];

    public $timestamps = false;
}
