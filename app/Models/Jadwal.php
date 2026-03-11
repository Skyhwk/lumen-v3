<?php

namespace App\Models;

use App\Models\Sector;

class Jadwal extends Sector
{
    protected $table = "jadwal";
    public $timestamps = false;

    protected $fillable = ["*"];

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
}
