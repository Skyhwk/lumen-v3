<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DraftAir extends Sector
{
    protected $table = 'draft_air';
    protected $guard = [];

    public $timestamps = false;

    public function instrument(){
        return $this->belongsTo(InstrumentIcp::class, 'no_sampel', 'no_sampel');
    }
}