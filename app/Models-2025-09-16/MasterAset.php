<?php

namespace App\Models;

use App\Models\Sector;

use Illuminate\Database\Eloquent\SoftDeletes;

class MasterAset extends Sector
{
    use softDeletes;

    protected $table = "master_aset";
    protected $guarded = ['id'];

    public function kategori_aset()
    {
        return $this->belongsTo(MasterKategoriAset::class, 'kategori');
    }

    public function sub_kategori_aset()
    {
        return $this->belongsTo(MasterSubKategoriAset::class, 'sub_kategori');
    }

    // Tambahan
    public function depresiasi_asets()
    {
        // return $this->hasOne(DepresiasiAset::class, 'id_master_aset', 'id');
        return $this->hasMany(DepresiasiAset::class, 'id_master_aset', 'id');
    }
}
