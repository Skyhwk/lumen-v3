<?php

namespace App\Models;

use App\Models\Sector;

class HistoriPrinting extends Sector
{
    protected $table = 'histori_printing';
    public $timestamps = false;

    protected $fillable = ['filename', 'printer', 'karyawan', 'timestamp', 'printer', 'destination'];
}