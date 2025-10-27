<?php

namespace App\Models;

use App\Models\Sector;

class FeeSampling extends Sector
{
    protected $table = 'fee_sampling';

    public $timestamps = false;
    protected $guarded = [];
   
}