<?php

namespace App\Models;

use App\Models\OrderHeader;

class MasterPelangganBackup extends Sector
{
    protected $table = 'master_pelanggan_backup';

    protected $with = ['kontak_pelanggan'];

    protected $fillable = [
        'id_cabang',
        'no_urut',
        'id_pelanggan',
        'nama_pelanggan',
        'wilayah',
        'kategori_pelanggan',
        'sub_kategori',
        'npwp',
        'bahan_pelanggan',
        'merk_pelanggan',
        'sales_penanggung_jawab',
        'sales_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
        'is_active',
    ];

    public $timestamps = false;

    public function kontak_pelanggan()
    {
        return $this->hasMany(KontakPelangganBackup::class, 'pelanggan_id')->where('is_active', true);
    }

    public function alamat_pelanggan()
    {
        return $this->hasMany(AlamatPelangganBackup::class, 'pelanggan_id')->where('is_active', true);
    }

    public function pic_pelanggan()
    {
        return $this->hasMany(PicPelangganBackup::class, 'pelanggan_id')->where('is_active', true);
    }

    public function order_customer()
    {
        return $this->hasMany(OrderHeader::class, 'id_pelanggan', 'id_pelanggan')
            ->where('is_active', 1)->orderBy('id', 'desc');
    }
}
