<?php

namespace App\Models;

use App\Models\Sector;

class DokumenSkppa extends Sector
{
    protected $table = 'dokumen_skppa';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'jabatan_penanggung_jawab' => 'array',
        'nama_penanggung_jawab' => 'array',
    ];

    public function order(){
        return $this->belongsTo(OrderHeader::class, 'id_order', 'id');
    }
}