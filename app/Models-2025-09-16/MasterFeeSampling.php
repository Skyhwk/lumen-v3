<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterFeeSampling extends Sector
{
    protected $table = 'master_fee_sampling';

    protected $guarded = [];

    public $timestamps = false;  // Set to true if you have created_at and updated_at columns
}
