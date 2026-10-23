<?php

namespace App\Models;

class KontakPelangganBackup extends Sector
{
    protected $table = 'kontak_pelanggan_backup';

    protected $fillable = [
        'pelanggan_id',
        'no_tlp_perusahaan',
        'email_perusahaan',
        'created_by',
        'updated_by',
        'deleted_by',
        'is_active',
    ];

    public $timestamps = false;

    public function pelanggan()
    {
        return $this->belongsTo(MasterPelangganBackup::class, 'pelanggan_id');
    }
}
