<?php

namespace App\Models;

use App\Models\Sector;

class KategoriBarang extends Sector
{
    protected $table = 'kategori_barang';
    public $timestamps = false;
    protected $fillable = [
        'id_cabang',
        'kategori',
        'created_at',
        'created_by',
        'is_active'
    ];
}