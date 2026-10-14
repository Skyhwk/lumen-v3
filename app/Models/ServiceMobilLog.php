<?php

namespace App\Models;

class ServiceMobilLog extends Sector
{
    protected $table = 'service_mobil_log';
    public $timestamps = false;

    protected $fillable = [
        'service_mobil_id',
        'jenis_tindakan',
        'nilai_sebelum',
        'nilai_sesudah',
        'alasan',
        'catatan',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'nilai_sebelum' => 'array',
        'nilai_sesudah' => 'array',
    ];

    public function service()
    {
        return $this->belongsTo(ServiceMobil::class, 'service_mobil_id');
    }
}
