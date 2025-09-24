<?php

namespace App\Models;

use App\Models\Sector;

use Illuminate\Database\Eloquent\SoftDeletes;

class MasterAset extends Sector
{
    use softDeletes;

    protected $table = "master_aset";
    protected $guarded = ['id'];

    
    protected $appends = ['current_lokasi', 'current_ruang'];

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

    public function mutasi()
    {
        return $this->hasMany(MutasiAsetPerusahaan::class, 'id_aset_perusahaan', 'id')->orderBy('mutasi_at', 'desc');
    }

    public function getCurrentLokasiAttribute()
    {
        $mutasi = $this->relationLoaded('mutasi')
            ? $this->mutasi->first()
            : $this->mutasi()->first();

        return $mutasi->lokasi_mutasi ?? $this->lokasi;
    }

    public function getCurrentRuangAttribute()
    {
        $mutasi = $this->relationLoaded('mutasi')
            ? $this->mutasi->first()
            : $this->mutasi()->first();

        return $mutasi->ruang_mutasi ?? $this->ruang;
    }
    
}
