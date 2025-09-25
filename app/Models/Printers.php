<?php

namespace App\Models;

use App\Models\Sector;

class Printers extends Sector
{
    protected $table = "printers";
    public $timestamps = false;

    protected $guarded = [];


    public function divisi()
    {
        return $this->hasOne('App\Models\MasterDivisi', 'id', 'id_divisi');
    }

  
}