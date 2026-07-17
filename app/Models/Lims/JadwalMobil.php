<?php
namespace App\Models\Lims;

use App\Models\Sector;

class JadwalMobil extends Sector
{
    protected $connection = 'lims';

    protected $table = "jadwal_mobil";
    public $timestamps = false;

    protected $guarded = [];
}