<?php

namespace App\Models;

use App\Models\Sector;

class RecordPermintaanBarang extends Sector
{
    protected $table = 'record_permintaan_barang';
    protected $fillable = [
        'id_cabang', 
        'request_id', 
        'timestamp', 
        'id_user', 
        'nama_karyawan', 
        'divisi', 
        'id_kategori', 
        'id_barang', 
        'kode_barang', 
        'nama_barang', 
        'jumlah',
        'keterangan', 
        'status',
        'process_time', 
        'submited', 
        'reminder',
        'note',
        'flag'
    ];

    public $timestamps = false;

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id');
    }

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_user', 'id');
    }
}