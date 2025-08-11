<?php

namespace App\Models;

use App\Models\Sector;

class AnalisInput extends Sector
{
    protected $table = 'analis_input';
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parameter()
    {
        return $this->hasOne(Parameter::class, 'id', 'parameter_id');
    }
}
