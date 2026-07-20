<?php 
namespace App\Models\Lims;
use App\Models\Sector;

class RekapMasukKerja extends Sector
{
    protected $connection = 'lims';

    protected $table = "rekap_masuk_kerja";
    public $timestamps = false;

}