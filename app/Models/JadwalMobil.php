<?php
namespace App\Models;

use App\Models\Sector;

class JadwalMobil extends Sector
{
    protected $table = "jadwal_mobil";
    public $timestamps = false;

    protected $guarded = [];

}