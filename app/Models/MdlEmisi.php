<?php

namespace App\Models;

use App\Models\Sector;

class MdlEmisi extends Sector
{
    protected $table = 'mdl_emisi';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function parameter()
    {
        return $this->belongsTo(Parameter::class)->where('is_active', true);
    }
}
