<?php

namespace App\Models\customer;

use App\Models\Sector;

class TeamMembers extends Sector
{
    protected $connection = "portal_customer";
    protected $table = "team_members";
    protected $guarded = ['id'];

    public $timestamps = false;
}
