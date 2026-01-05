<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class ClaimFeeExternal extends Sector
{
    protected $table = 'claim_fee_external';

    public $timestamps = false;

    protected $guarded = [];
}