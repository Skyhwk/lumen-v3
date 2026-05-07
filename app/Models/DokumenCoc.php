<?php

namespace App\Models;

use App\Models\Sector;

class DokumenCoc extends Sector
{
    protected $table = 'dokumen_coc';
    protected $guarded = ['id'];

    public $timestamps = false;
}
