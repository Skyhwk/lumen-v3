<?php

namespace App\Models;

class ServiceMobilDetail extends Sector
{
    protected $table = 'service_mobil_detail';
    public $timestamps = false;

    protected $fillable = [
        'service_mobil_id',
        'deskripsi_pekerjaan',
        'spare_part',
        'biaya',
        'urutan',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public function service()
    {
        return $this->belongsTo(ServiceMobil::class, 'service_mobil_id');
    }
}
