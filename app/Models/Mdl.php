<?php

namespace App\Models;

use App\Models\Sector;

class Mdl extends Sector
{
    protected $table = 'mdl';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'parameter',
        'value',
    ];

    // public function kategori(){
    //     return $this->belongsTo(KategoriBarang::class, 'id_kategori', 'id');
    // }

    // public function barang(){
    //     return $this->belongsTo(Barang::class, 'id_barang', 'id');
    // }
}