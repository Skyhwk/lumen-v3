<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class KebisinganHeader extends Sector{

    protected $table = 'kebisingan_header';
    public $timestamps = false;

    protected $guarded = [];

}