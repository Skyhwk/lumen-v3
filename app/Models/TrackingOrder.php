<?php

namespace App\Models;

use App\Models\Sector;

class TrackingOrder extends Sector
{
    protected $table = 'tracking_order';

    protected $guarded = [];

    protected $casts = [
        'tanggal_order' => 'date:Y-m-d',
        'tanggal_penawaran' => 'date:Y-m-d',
        'tanggal_awal_sampling' => 'date:Y-m-d',
        'tanggal_terakhir_lhp_rilis' => 'date:Y-m-d',
        'tanggal_kelompok' => 'date:Y-m-d',
        'nilai_invoice' => 'float',
        'nilai_pembayaran' => 'float',
        'nilai_pengurangan' => 'float',
        'revenue_invoice' => 'float',
        'total_discount' => 'decimal:2',
        'total_ppn' => 'decimal:2',
        'total_pph' => 'decimal:2',
        'biaya_akhir' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'total_revenue' => 'decimal:2',
        'is_lunas' => 'integer',
        'is_invoicing' => 'boolean',
        'is_selesai' => 'boolean',
        'total_lhp' => 'integer',
        'jumlah_lhp_selesai' => 'integer',
    ];

    public function orderHeader()
    {
        return $this->hasOne(OrderHeader::class, 'no_order', 'no_order');
    }

    public function pelanggan()
    {
        return $this->hasOne(MasterPelanggan::class, 'id_pelanggan', 'pelanggan_ID');
    }

    public function sales()
    {
        return $this->hasOne(MasterKaryawan::class, 'id', 'sales_id');
    }
}
