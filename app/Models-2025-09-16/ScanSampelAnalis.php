<?php

namespace App\Models;

use App\Models\Sector;

class ScanSampelAnalis extends Sector
{
    protected $table = 'scan_sampel_analis';

    public $timestamps = false;
    protected $guarded = [];

    public function t_ftc()
    {
        return $this->hasOne(Ftc::class, 'no_sample', 'no_sampel');
    }
}