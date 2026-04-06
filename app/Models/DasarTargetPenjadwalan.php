<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DasarTargetPenjadwalan extends Sector
{
    protected $table = 'dasar_perhitungan_target_penjadwalan';
    protected $guarded = [];
    public $timestamps = false;
}