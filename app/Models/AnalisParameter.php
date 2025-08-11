<?php

namespace App\Models;

use App\Models\Sector;

class AnalisParameter extends Sector
{
    protected $table = 'analis_parameter';
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parameter()
    {
        return $this->hasOne(Parameter::class, 'id', 'parameter_id');
    }

    public function input()
    {
        return $this->hasOne(AnalisInput::class, 'id', 'id_form');
    }

    public function template()
    {
        return $this->hasOne(TemplateStp::class, 'id', 'id_stp');
    }
}
