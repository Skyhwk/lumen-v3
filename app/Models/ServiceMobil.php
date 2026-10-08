<?php

namespace App\Models;

class ServiceMobil extends Sector
{
    protected $table = 'service_mobil';
    public $timestamps = false;

    protected $fillable = [
        'daftar_mobil_id',
        'plat_mobil',
        'merk_mobil',
        'tipe_mobil',
        'tanggal_mulai_rencana',
        'tanggal_selesai_rencana_awal',
        'tanggal_selesai_estimasi',
        'tanggal_mulai_aktual',
        'tanggal_selesai_aktual',
        'status',
        'keluhan',
        'nama_bengkel',
        'catatan',
        'kilometer',
        'catatan_hasil',
        'alasan_pembatalan',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'started_by',
        'started_at',
        'completed_by',
        'completed_at',
        'canceled_by',
        'canceled_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'kilometer' => 'integer',
    ];

    public const STATUS_DIJADWALKAN = 'dijadwalkan';
    public const STATUS_DALAM_PERBAIKAN = 'dalam_perbaikan';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    public function mobil()
    {
        return $this->belongsTo(DaftarMobil::class, 'daftar_mobil_id');
    }

    public function details()
    {
        return $this->hasMany(ServiceMobilDetail::class, 'service_mobil_id');
    }

    public function logs()
    {
        return $this->hasMany(ServiceMobilLog::class, 'service_mobil_id');
    }

    public function attachments()
    {
        return $this->hasMany(ServiceMobilAttachment::class, 'service_mobil_id');
    }
}
