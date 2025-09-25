<?php

namespace App\Models;

use App\Models\Sector;

class TabelRegulasi extends Sector
{
    protected $table = 'tabel_regulasi';
    protected $guarded = ['id'];

    public $timestamps = false;
}
