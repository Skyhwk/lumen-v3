<?php

namespace App\Models\customer;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Team extends Sector
{
    protected $connection = "portal_customer";

    protected $table = "teams";
    public $timestamps = false;

    protected $guarded = ['id'];
}
