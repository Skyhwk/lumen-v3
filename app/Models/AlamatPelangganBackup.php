<?php

namespace App\Models;

class AlamatPelangganBackup extends Sector
{
    protected $table = 'alamat_pelanggan_backup';

    protected $fillable = [
        'pelanggan_id',
        'type_alamat',
        'alamat',
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
