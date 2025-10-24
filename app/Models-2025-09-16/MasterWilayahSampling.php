<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterWilayahSampling extends Sector
{
    protected $table = 'master_wilayah_sampling';
    protected $guard = [];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'id_cabang');
    }

    public $timestamps = false;
}