<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataPerbantuan extends Sector
{
    protected $table = "data_perbantuan";
    public $timestamps = false;
    protected $guarded = [];

    public function sales()
    {
        return $this->belongsTo(MasterKaryawan::class, 'sales_id', 'id');
    }

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id', 'id');
    }
    
}