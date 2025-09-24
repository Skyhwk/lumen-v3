<?php

namespace App\Models;

use App\Models\Sector;

class ParameterAnalisa extends Sector
{
    protected $table = 'parameter_analisa';
    public $timestamps = false;

    protected $fillable = [
        'no_order',
        'no_sampel',
        'tanggal_order',
        'parameter',
        'tanggal_analisa'
    ];
}
