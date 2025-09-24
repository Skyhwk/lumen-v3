<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PraNoSample extends Sector
{
    protected $table = "pra_no_sample";
    public $timestamps = false;

    protected $guarded = [];
}