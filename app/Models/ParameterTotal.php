<?php

namespace App\Models;

use App\Models\Sector;

class ParameterTotal extends Sector
{
    protected $table = 'parameter_total';
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parameter()
    {
        return $this->hasOne(Parameter::class, 'id', 'parameter_id');
    }
}
