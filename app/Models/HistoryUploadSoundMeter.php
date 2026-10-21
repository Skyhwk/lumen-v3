<?php

namespace App\Models;

class HistoryUploadSoundMeter extends Sector
{
    protected $table = 'history_upload_sound_meter';

    protected $guarded = [];

    protected $casts = [
        'daftar_no_sampel' => 'array',
        'daftar_shift' => 'array',
        'detail_gagal' => 'array',
        'is_active' => 'boolean',
        'timestamp_awal' => 'datetime',
        'timestamp_akhir' => 'datetime',
        'uploaded_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(DeviceIntilab::class, 'kode_device', 'kode');
    }
}
