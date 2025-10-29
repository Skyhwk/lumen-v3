<?php

namespace App\Models;

use App\Models\Sector;

class AlasanVoidQt extends Sector
{
    protected $table = 'alasan_void_quotation';
    protected $guarded = ['id'];

    public $timestamps = false;
}
