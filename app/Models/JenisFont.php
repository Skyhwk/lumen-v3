<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class JenisFont extends Sector
{
    protected $table = "jenis_font";
    public $timestamps = false;

    protected $guarded = [];

}