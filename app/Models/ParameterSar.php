<?php

namespace App\Models;

use App\Models\Sector;

class ParameterSar extends Sector
{
    protected $table = 'parameter_sar';
    public $timestamps = false;
    protected $guarded = [];

    public function hargaParameter()
    {
        return $this->hasOne(HargaParameter::class, 'id_parameter', 'id_parameter')
            ->where('is_active', true);
    }
}