<?php

namespace App\Models;

use App\Models\Sector;

use Illuminate\Database\Eloquent\SoftDeletes;

class MdlEmisi extends Sector
{
    use SoftDeletes;

    protected $table = 'mdl_emisi';
    protected $guarded = ['id'];

    public function parameter()
    {
        return $this->belongsTo(Parameter::class);
    }
}
