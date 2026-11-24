<?php

namespace App\Models;

class ServiceMobil extends Sector
{
    protected $table = 'service_mobil';
    public $timestamps = false;

    protected $fillable = [
        'daftar_mobil_id',
        'plat_mobil',
        'tanggal_mulai_rencana',
        'tanggal_selesai_estimasi',
        'tanggal_mulai_aktual',
        'tanggal_selesai_aktual',
        'status',
        'keluhan',
        'nama_bengkel',
        'catatan',
        'kilometer',
        'catatan_hasil',
        'hasil_pekerjaan',
        'biaya',
        'alasan_pembatalan',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'kilometer' => 'integer',
        'biaya' => 'array',
    ];

    public const STATUS_DIJADWALKAN = 'dijadwalkan';
    public const STATUS_DALAM_PERBAIKAN = 'dalam_perbaikan';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    public function mobil()
    {
        return $this->belongsTo(DaftarMobil::class, 'daftar_mobil_id');
    }

    /** Merk dari master (eks snapshot kolom `merk_mobil`). */
    public function getMerkMobilAttribute(): ?string
    {
        if ($this->relationLoaded('mobil') && $this->mobil) {
            return $this->mobil->merk_mobil;
        }

        return $this->attributes['merk_mobil'] ?? null;
    }

    /** Tipe dari master (eks snapshot kolom `tipe_mobil`). */
    public function getTipeMobilAttribute(): ?string
    {
        if ($this->relationLoaded('mobil') && $this->mobil) {
            return $this->mobil->tipe_mobil;
        }

        return $this->attributes['tipe_mobil'] ?? null;
    }

    /**
     * Bentuk array details lama (kompatibilitas API / DataTables) dari kolom header.
     */
    public function completionDetailsForApi(): array
    {
        if ($this->status !== self::STATUS_SELESAI) {
            return [];
        }

        if (!$this->hasil_pekerjaan && ($this->biaya === null || $this->biaya === [])) {
            return [];
        }

        return [
            [
                'deskripsi_pekerjaan' => $this->hasil_pekerjaan,
                'biaya' => $this->biaya,
            ],
        ];
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
