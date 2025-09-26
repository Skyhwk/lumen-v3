<?php

namespace App\Models\customer;

use App\Models\Sector;

class TeamMember extends Sector
{
    protected $connection = "portal_customer";

    protected $table = "team_members";
    public $timestamps = false;

    protected $guarded = ['id'];
}
