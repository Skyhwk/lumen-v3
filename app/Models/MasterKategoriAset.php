<?php

namespace App\Models;

use App\Models\Sector;

class MasterKategoriAset extends Sector
{
    protected $table = "master_kategori_aset";
    protected $guarded = ['id'];

    public $timestamps = false;

    public function sub_kategori()
    {
        return $this->hasMany(MasterSubKategoriAset::class, 'kategori_aset_id');
    }
}
