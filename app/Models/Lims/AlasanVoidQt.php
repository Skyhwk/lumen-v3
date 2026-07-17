<?php

namespace App\Models\Lims;

use App\Models\Sector;

class AlasanVoidQt extends Sector
{
    protected $connection = 'lims';

    protected $table = 'alasan_void_quotation';
    protected $guarded = ['id'];

    public $timestamps = false;
}
