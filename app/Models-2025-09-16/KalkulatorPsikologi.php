<?php

namespace App\Models;

use App\Models\Sector;

class KalkulatorPsikologi extends Sector {
    protected $table = "kalkulator_psikologi";
    protected $guarded = ['id'];
    public $timestamps = false;
}