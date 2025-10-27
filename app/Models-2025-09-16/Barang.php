<?php

namespace App\Models;

use App\Models\Sector;

class Barang extends Sector
{
    protected $table = 'barang';
    public $timestamps = false;
    protected $fillable = [
        'id_cabang',
        'kode_barang',
        'nama_barang',
        'id_kategori',
        'merk',
        'satuan',
        'ukuran',
        'min',
        'awal',
        'akhir',
        'is_active'
    ];

    public function kategori(){
        return $this->belongsTo(KategoriBarang::class, 'id_kategori');
    }
}