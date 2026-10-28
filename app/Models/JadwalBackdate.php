<?php

namespace App\Models;

use App\Models\Sector;

class JadwalBackdate extends Sector
{
    protected $connection = 'mysql';
    protected $table = 'jadwal_backdate';
    public $timestamps = false;

    protected $guarded = ['id'];

    public function samplingPlan()
    {
        return $this->belongsTo(SamplingPlan::class, "id_sampling", "id");
    }

    public function persiapanHeader()
    {
        return $this->belongsTo(PersiapanSampelHeader::class, "no_quotation", "no_quotation");
    }

    public function orderDetail()
    {
        return $this->hasMany(OrderDetail::class, "no_quotation", "no_quotation");
    }

    public function orderHeader()
    {
        return $this->belongsTo(OrderHeader::class, "no_quotation", "no_document");
    }

    public function quotationKontrakH()
    {
        return $this->belongsTo(QuotationKontrakH::class, "no_quotation", "no_document");
    }

    public function quotationNonKontrak()
    {
        return $this->belongsTo(QuotationNonKontrak::class, "no_quotation", "no_document");
    }

    public function jadwalMobil()
    {
        return $this->belongsTo(JadwalMobil::class, 'kendaraan', 'plat_mobil');
    }
}

