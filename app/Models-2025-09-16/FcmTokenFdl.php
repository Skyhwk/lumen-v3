<?php

namespace App\Models;

use App\Models\Sector;
use Illuminate\Database\Eloquent\SoftDeletes;

class FcmTokenFdl extends Sector
{
    protected $table = 'fcm_token_fdl';

    protected $guarded = [];
}
