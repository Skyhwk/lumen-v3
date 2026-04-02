<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class KalkulasiTargetPenjadwalan extends Sector
{
    protected $table = 'kalkulasi_target_penjadwalan';
    protected $guarded = [];
    public $timestamps = true;
}