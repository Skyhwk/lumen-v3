<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Invoice extends Sector
{
    protected $table = 'invoice';
    protected $guarded = ['id'];


    public $timestamps = false;
    protected $casts = [
        'keterangan_tambahan' => 'array'
    ];
    
    public function link()
    {
        return $this->hasOne(GenerateLink::class, 'id_quotation', 'id')
            ->where('quotation_status', 'INVOICE');
    }

    public function perusahaan()
    {
        return $this->belongsTo(MasterPelanggan::class, 'pelanggan_id', 'id_pelanggan');
    }

    public function followup_billings()
    {
        return $this->hasMany(FollowupBilling::class, 'no_invoice', 'no_invoice')->where('is_active', true);
    }

    public function recordPembayaran()
    {
        return $this->hasMany(RecordPembayaranInvoice::class, 'no_invoice', 'no_invoice')->where('is_active', true);
    }

    public function recordWithdraw()
    {
        return $this->hasMany(Withdraw::class, 'no_invoice', 'no_invoice');
    }
    public function cekRevisi()
    {
        return $this->belongsTo(OrderHeader::class, 'no_quotation', 'no_document')->where('is_revisi', true);
    }

    public function quotationNonKontrak()
    {
        return $this->hasOne(QuotationNonKontrak::class, 'no_document', 'no_quotation');
    }

    public function quotationKontrak()
    {
        return $this->hasOne(QuotationKontrakH::class, 'no_document', 'no_quotation');
    }

    public function kontrak()
    {
        return $this->belongsTo(QuotationKontrakH::class, 'no_quotation', 'no_document')
                    ->with('detail');
    }

    public function nonKontrak()
    {
        return $this->belongsTo(QuotationNonKontrak::class, 'no_quotation', 'no_document');
    }

    public function Quotation()
    {
        return $this->kontrak ?? $this->nonKontrak ?? null;
        
    }

    public function orderHeaderQuot()
    {
        return $this->belongsTo(OrderHeader::class, 'no_quotation', 'no_document');
    }

    public function orderHeader()
    {
        return $this->belongsTo(OrderHeader::class, 'no_order', 'no_order');
    }

}