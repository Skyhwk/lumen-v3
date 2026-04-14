<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterBotol extends Sector
{
    protected $table = 'master_botol';

    protected $guarded = [];

    public $timestamps = false;
}
