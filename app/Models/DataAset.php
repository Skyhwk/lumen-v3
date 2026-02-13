<?php

namespace App\Models;

use App\Models\Sector;

class DataAset extends Sector
{
    protected $table = 'data_aset';

    public function kategori()
    {
        return $this->belongsTo(MasterKategoriAset::class, 'id_kategori_aset', 'id');
    }

    public function sub_kategori()
    {
        return $this->belongsTo(MasterSubKategoriAset::class, 'id_subkategori_aset', 'id');
    }
}