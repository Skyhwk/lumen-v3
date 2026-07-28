<?php

namespace App\Models;

use App\Models\Sector;

class BasSampelSelesai extends Sector
{
    protected $table = 'bas_sampel_selesai';

    protected $fillable = [
        'no_quotation',
        'no_order',
        'no_sampel',
        'kategori',
        'sub_kategori',
        'tanggal_sampling',
        'status'
    ];
}
