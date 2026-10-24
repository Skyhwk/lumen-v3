<?php

namespace App\Models\ppi;

use App\Models\Sector;

class TeamMembers extends Sector
{
    protected $connection = 'portal_pelanggan';
    protected $table = 'team_members';
    protected $guarded = ['id'];

    public $timestamps = false;
}
