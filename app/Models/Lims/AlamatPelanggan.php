<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class AlamatPelanggan extends Sector
{
    protected $connection = 'lims';


    protected $table = 'alamat_pelanggan';

    protected $fillable = [
        'pelanggan_id', 
        'type_alamat', 
        'alamat', 
        'created_by', 
        'updated_by', 
        'deleted_by', 
        'is_active'
    ];

    public $timestamps = false;

    public function pelanggan()
    {
        return $this->belongsTo(MasterPelanggan::class, 'pelanggan_id');
    }
}
