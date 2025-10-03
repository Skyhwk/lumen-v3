<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use Carbon\Carbon;

class MasterPelanggan extends Sector
{
    protected $table = 'master_pelanggan';

    protected $with = ['kontak_pelanggan'];

    protected $fillable = [
        'id_cabang',
        'no_urut',
        'id_pelanggan',
        'nama_pelanggan',
        'wilayah',
        'kategori_pelanggan',
        'sub_kategori',
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
        'is_active'
    ];

    public $timestamps = false;

    public function kontak_pelanggan()
    {
        return $this->hasMany(KontakPelanggan::class, 'pelanggan_id')->where('is_active', true);
    }

    public function alamat_pelanggan()
    {
        return $this->hasMany(AlamatPelanggan::class, 'pelanggan_id')->where('is_active', true);
    }

    public function pic_pelanggan()
    {
        return $this->hasMany(PicPelanggan::class, 'pelanggan_id')->where('is_active', true);
    }

    public function order()
    {
        return $this->hasMany(OrderHeader::class, 'id_pelanggan', 'id_pelanggan')
            ->where('is_active', 1)->whereYear('tanggal_order', '>=', Carbon::now()->year)->orderBy('id', 'desc');
    }

    public function order_customer()
    {
        return $this->hasMany(OrderHeader::class, 'id_pelanggan', 'id_pelanggan')
            ->where('is_active', 1)->orderBy('id', 'desc');
    }

    public function currentSales()
    {
        return $this->belongsTo(MasterKaryawan::class, 'sales_id');
    }

    public function historySales()
    {
        return $this->hasOne(HistoryPerubahanSales::class, 'id_pelanggan', 'id_pelanggan')->orderByDesc('tanggal_rotasi');
    }

    public function latestOrder()
    {
        return $this->hasOne(OrderHeader::class, 'id_pelanggan', 'id_pelanggan')->orderByDesc('tanggal_order');
    }
}
