<?php

namespace App\Models\Lims;

use App\Models\Sector;

class KelengkapanKonfirmasiQs extends Sector
{
    protected $connection = 'lims';

    protected $table = 'kelengkapan_konfirmasi_qs';
    public $timestamps = false;
    public $guarded = [];
}
