<?php

namespace App\Models;

use App\Models\Sector;

use Illuminate\Database\Eloquent\SoftDeletes;

class MdlUdara extends Sector
{
    use SoftDeletes;

    protected $table = 'mdl_udara';
    protected $guarded = ['id'];

    public function parameter()
    {
        return $this->belongsTo(Parameter::class);
    }
}
