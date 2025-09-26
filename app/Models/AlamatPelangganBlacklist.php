<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class AlamatPelangganBlacklist extends Sector
{

    protected $table = 'alamat_pelanggan_blacklist';

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

    public function pelanggan_blacklist()
    {
        return $this->belongsTo(MasterPelangganBlacklist::class, 'pelanggan_id');
    }
}
