<?php

namespace App\Models;

class SuratPeringatanDisipliner extends Sector
{
    public const TYPE_SP1 = 'SP1';
    public const TYPE_SP2 = 'SP2';
    public const TYPE_SP3 = 'SP3';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'surat_peringatan_disipliner';

    protected $guarded = ['id'];

    protected $casts = [
        'tanggal_terbit' => 'date',
        'tanggal_kejadian' => 'date',
        'tanggal_berlaku_mulai' => 'date',
        'tanggal_berlaku_selesai' => 'date',
        'is_active' => 'boolean',
        'tahun_nomor' => 'integer',
        'urutan_nomor' => 'integer',
        'masa_berlaku_bulan' => 'integer',
        'file_size' => 'integer',
    ];

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }

    public function suratSebelumnya()
    {
        return $this->belongsTo(self::class, 'id_surat_sebelumnya');
    }
}
