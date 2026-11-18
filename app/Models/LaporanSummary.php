<?php

namespace App\Models;

class LaporanSummary extends Sector
{
    protected $table = 'laporan_summary';

    protected $guarded = ['id'];

    public $timestamps = false;
}
