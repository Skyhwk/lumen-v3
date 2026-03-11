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
        return $this->hasOne(OrderHeader::class, 'id_pelanggan', 'id_pelanggan')->where('is_active', true)->orderByDesc('tanggal_order');
    }

    public function latestKontrakQuotation()
    {
        return $this->hasOne(QuotationKontrakH::class, 'pelanggan_ID', 'id_pelanggan')->where('is_active', true)->orderByDesc('id');
    }

    public function latestNonKontrakQuotation()
    {
        return $this->hasOne(QuotationNonKontrak::class, 'pelanggan_ID', 'id_pelanggan')->where('is_active', true)->orderByDesc('id');
    }

    public function latestDFUS()
    {
        return $this->hasOne(DFUS::class, 'id_pelanggan', 'id_pelanggan')
            ->orderByDesc('tanggal');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'pelanggan_id', 'id_pelanggan')
            ->selectRaw('no_order, no_invoice, MAX(no_quotation) as no_quotation, periode, tgl_invoice, tgl_jatuh_tempo, SUM(nilai_tagihan) as nilai_tagihan, pelanggan_id')
            ->where('is_active', 1)
            ->groupBy('no_order', 'no_invoice', 'periode', 'tgl_invoice', 'tgl_jatuh_tempo', 'pelanggan_id')
            ->orderByDesc('tgl_invoice')
            ->with(['recordPembayaran' => function($query) {
                $query->select('no_invoice', 'tgl_pembayaran', 'nilai_pembayaran');
            }, 'recordWithdraw' => function($query) {
                $query->select('no_invoice', 'nilai_pembayaran', 'keterangan_pelunasan');
            }]);
    }

    public function getLatestDFUSMatchAttribute()
    {
        return $this->latestDFUS && 
            $this->latestDFUS->sales_penanggung_jawab === $this->sales_penanggung_jawab
            ? $this->latestDFUS
            : null;
    }

    public function quotasiNonKontrak()
    {
        return $this->hasMany(QuotationNonKontrak::class, 'pelanggan_ID', 'id_pelanggan');
    }

    public function quotasiKontrak()
    {
        return $this->hasMany(QuotationKontrakH::class, 'pelanggan_ID', 'id_pelanggan');
    }
}
