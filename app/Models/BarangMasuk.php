<?php

namespace App\Models;

use App\Models\Sector;

class BarangMasuk extends Sector
{
    protected $table = 'barang_masuk';
    public $timestamps = false;
    protected $fillable = [
        'id_cabang',
        'id_barang',
        'id_kategori',
        'kode_barang',
        'jumlah',
        'harga_satuan',
        'harga_total',
        'created_at',
        'created_by'
    ];

    public function kategori(){
        return $this->belongsTo(KategoriBarang::class, 'id_kategori', 'id');
    }

    public function barang(){
        return $this->belongsTo(Barang::class, 'id_barang', 'id');
    }
}