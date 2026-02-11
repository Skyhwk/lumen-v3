<?php

namespace App\Models;

use App\Models\Sector;

class MasterSubKategoriAset extends Sector
{
    protected $table = "master_sub_kategori_aset";
    protected $guarded = ['id'];

    public $timestamps = false;

    public function kategori()
    {
        return $this->belongsTo(MasterKategoriAset::class, 'id_kategori', 'id');
    }
}
