<?php

namespace App\Models;

class PicPelangganBackup extends Sector
{
    protected $table = 'pic_pelanggan_backup';

    protected $fillable = [
        'pelanggan_id',
        'type_pic',
        'nama_pic',
        'jabatan_pic',
        'no_tlp_pic',
        'wa_pic',
        'email_pic',
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
