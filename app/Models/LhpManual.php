<?php

namespace App\Models;

class LhpManual extends Sector
{
    protected $table = 'lhp_manual';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'parameter_uji' => 'array',
        'tanggal_sampling' => 'date',
        'tanggal_terima' => 'date',
        'tanggal_lhp' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function orderHeader()
    {
        return $this->belongsTo(OrderHeader::class, 'no_order', 'no_order');
    }

    /** Alias konsisten dengan model LinkLhp. */
    public function order()
    {
        return $this->orderHeader();
    }

    /**
     * Satu no LHP (CFR) dapat mencakup banyak baris order_detail.
     */
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'cfr', 'no_lhp')
            ->where('is_active', 1);
    }

    /**
     * Quotation gabungan (non-kontrak & kontrak) via all_quot.
     */
    public function quotation()
    {
        return $this->belongsTo(AllQuote::class, 'no_quotation', 'no_document');
    }

    /** Quotation non-kontrak (request_quotation). */
    public function quotationNonKontrak()
    {
        return $this->belongsTo(QuotationNonKontrak::class, 'no_quotation', 'no_document');
    }

    /** Quotation kontrak (request_quotation_kontrak_H). */
    public function quotationKontrak()
    {
        return $this->belongsTo(QuotationKontrakH::class, 'no_quotation', 'no_document');
    }

    /**
     * QR validasi LHP — file_qr menyimpan nama file tanpa ekstensi .svg.
     */
    public function qrDocument()
    {
        return $this->belongsTo(QrDocument::class, 'file_qr', 'file');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }
}
