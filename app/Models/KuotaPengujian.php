<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class KuotaPengujian extends Sector {
    protected $table = "kuota_pengujian";
    public $timestamps = false;

    protected $guarded = [];

    public function pelanggan() {
        return $this->belongsTo(MasterPelanggan::class, 'pelanggan_ID', 'id_pelanggan');
    }

    public function parameter() {
        return $this->belongsTo(Parameter::class, 'id_parameter', 'id');
    }

    public function kategori() {
        return $this->belongsTo(MasterKategori::class, 'id_kategori', 'id');
    }

    public function histories() {
        return $this->hasMany(HistoryKuotaPengujian::class, 'id_kuota', 'id');
    }
}