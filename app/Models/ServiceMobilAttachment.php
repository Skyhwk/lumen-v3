<?php

namespace App\Models;

class ServiceMobilAttachment extends Sector
{
    protected $table = 'service_mobil_attachment';
    public $timestamps = false;

    protected $fillable = [
        'service_mobil_id',
        'jenis_lampiran',
        'nama_file',
        'path_file',
        'mime_type',
        'ukuran_byte',
        'created_by',
        'created_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'ukuran_byte' => 'integer',
    ];

    public function service()
    {
        return $this->belongsTo(ServiceMobil::class, 'service_mobil_id');
    }
}
