<?php

namespace App\Models;

use App\Models\Sector;

class MdlUdara extends Sector
{
    protected $table = 'mdl_udara';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function parameter()
    {
        return $this->belongsTo(Parameter::class)->where('is_active', true);
    }
}
