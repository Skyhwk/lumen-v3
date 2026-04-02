<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterTargetPenjadwalan extends Sector
{
    protected $table = 'master_target_penjadwalan';
    protected $guarded = [];
    public $timestamps = false;
}