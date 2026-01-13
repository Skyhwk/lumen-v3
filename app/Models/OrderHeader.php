<?php

namespace App\Models;

use App\Models\Sector;

class OrderHeader extends Sector
{
    protected $table = "order_header";
    public $timestamps = false;
    protected $guarded = [];

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'no_order', 'no_order')->where('is_active', true);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'no_order', 'no_order')->where('is_active', true);
    }

    public function all_quote()
    {
        return $this->hasMany(AllQuote::class, 'no_document', 'no_document');
    }

    public function orderDetail()
    {
        return $this->hasMany(OrderDetail::class, 'id_order_header', 'id')->where('is_active', true);
    }

    public function codingSampling()
    {
        return $this->hasMany(CodingSampling::class, 'id_order_header');
    }

    public function jadwal()
    {
        return $this->hasMany(Jadwal::class, 'no_quotation', 'no_document');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user2()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // public function qoutationk()
    // {
    //     return $this->belongsTo('App\Models\RequestQuotationKontrakH', 'no_document', 'no_document');
    // }

    // public function qoutation()
    // {
    //     return $this->belongsTo('App\Models\RequestQuotation', 'no_document', 'no_document');
    // }

    public function samplingPlan()
    {
        return $this->hasMany(SamplingPlan::class, 'no_quotation', 'no_document')
            ->where('is_active', true)
            ->where('status', 1)
            ->select(['id', 'quotation_id', 'no_quotation', 'periode_kontrak', 'status_quotation', 'is_approved']);
    }

    public function docCodeSampling()
    {
        return $this->hasMany(DocumentCodingSample::class, 'no_quotation', 'no_document');
    }

    public function getInvoice()
    {
        return $this->hasMany(Invoice::class, 'no_quotation', 'no_document')->where('is_active', true);
    }

    public function sampling()
    {
        return $this->hasMany(samplingPlan::class, 'no_quotation', 'no_document')->where('is_active', true)->with('jadwal');
    }

    public function quotationkontrak()
    {
        return $this->hashOne(QuotationKontrakH::class, 'no_document', 'no_document');
    }

    public function quotation()
    {
        return $this->hashOne(QuotationNonKontrak::class, 'no_document', 'no_document');
    }

    // Jika non-kontrak (mengandung "QT" saja)
    public function quotationNonKontrak()
    {
        return $this->hasOne(QuotationNonKontrak::class, 'no_document', 'no_document');
    }

    // Jika dokumen kontrak (mengandung "QTC")
    public function quotationKontrakH()
    {
        return $this->hasOne(QuotationKontrakH::class, 'no_document', 'no_document');
    }

    public function scopeWithTypeModelSub($query)
    {
        $query->with(['quotation', 'quotationkontrak'])
            ->where(function ($query) {
                $query->where('status_quotation', 'kontrak')
                    ->orWhere('status_quotation', '!=', 'kontrak')
                    ->orWhereNull('status_quotation');
            });
    }


    public function persiapanSampel()
    {
        return $this->hasOne(PersiapanSampelHeader::class, 'no_order', 'no_order');
    }

    public static function getCustomerSummary($date)
    {
        return self::selectRaw('sales_id, COUNT(*) as total_orders,
            SUM(CASE WHEN id IS NULL THEN 1 ELSE 0 END) as pelanggan_baru,
            SUM(CASE WHEN id IS NOT NULL THEN 1 ELSE 0 END) as pelanggan_lama')
            ->whereDate('created_at', $date)
            ->groupBy('sales_id');
    }

    public function getLhpsAirHeader()
    {
        return $this->hasMany(LhpsAirHeader::class, 'no_order', 'no_order');
    }
    public function getLhpsEmisiHeader()
    {
        return $this->hasMany(LhpsEmisiHeader::class, 'no_order', 'no_order');
    }
    public function getLhpsEmisiCHeader()
    {
        return $this->hasMany(LhpsEmisiCHeader::class, 'no_order', 'no_order');
    }
    public function getLhpsGeteranHeader()
    {
        return $this->hasMany(LhpsGetaranHeader::class, 'no_order', 'no_order');
    }
    public function getLhpsKebisinganHeader()
    {
        return $this->hasMany(LhpsKebisinganHeader::class, 'no_order', 'no_order');
    }
    public function getLhpsLinkunganHeader()
    {
        return $this->hasMany(LhpsLingHeader::class, 'no_order', 'no_order');
    }
    public function getLhpsMedanLMHeader()
    {
        return $this->hasMany(LhpsMedanLMHeader::class, 'no_order', 'no_order');
    }
    public function getLhpsPencahayaanHeader()
    {
        return $this->hasMany(LhpsPencahayaanHeader::class, 'no_order', 'no_order');
    }
    public function getLhpsSinarUVHeader()
    {
        return $this->hasMany(LhpsSinarUVHeader::class, 'no_order', 'no_order');
    }

    public function perusahaan()
    {
        return $this->belongsTo(MasterPelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }
    public function SampelDiantar()
    {
        return $this->hasMany(SampelDiantar::class, 'no_quotation', 'no_document')->with(['detail']);
    }
    public function qsd()
    {
        return $this->hasOne(Qsd::class, 'order_header_id', 'id')->with('document');
    }
    public function lhpp_psikologi()
    {
        return $this->hasOne(LhppUdaraPsikologiHeader::class, 'no_order', 'no_order');
    }
    public function lhp_psikologi()
    {
        return $this->hasOne(LhpUdaraPsikologiHeader::class, 'no_order', 'no_order');
    }
    public function persiapanHeader()
    {
        return $this->hasMany(PersiapanSampelHeader::class, 'no_order', 'no_order')->where('is_active', true);
    }

    public function coverLhp()
    {
        return $this->hasOne(CoverLhp::class, 'no_order', 'no_order')->where('is_active', true);
    }
    
    public function holdHp()
    {
        return $this->hasOne(HoldHp::class, 'no_order', 'no_order');
    }

    public function getQuotationFinalAttribute()
    {
        return $this->quotationKontrakH ?? $this->quotationNonKontrak ?? null;
    }

    public function emailLhp()
    {
        return $this->hasMany(EmailLhp::class, 'id_pelanggan', 'id_pelanggan');
    }

    public function sales()
    {
        return $this->belongsTo(MasterKaryawan::class, 'sales_id', 'id');
    }

    public function persiapanSampelHeaderFdl()
    {
        return $this->hasOne(PersiapanSampelHeader::class, 'no_quotation', 'no_document')->where('is_active', true);
    }
}
