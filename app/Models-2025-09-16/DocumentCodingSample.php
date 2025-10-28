<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DocumentCodingSample extends Sector
{

    protected $table = 'doc_coding_sample';
    protected $guarded = [];

    public $timestamps = false;

    public function printby()
    {
        return $this->belongsTo(MasterKaryawan::class, 'printed_by', 'id');
    }

    public function quotationNon () {
        return $this->belongsTo(QuotationNonKontrak::class, 'no_document', 'no_document');
    }

    public function quotationContract () {
        return $this->belongsTo(QuotationKontrakH::class, 'no_document', 'no_document');
    }


    public function order ()
    {
        return $this->belongsTo(OrderHeader::class, 'no_document', 'no_document')
        ->with('orderDetail');
    }

    public function scopeWithType () {
        $query->with(['quotationNon', 'quotationContract'])
        ->where(function($query){
            $query->where('status_quotation', 'kontrak');
            $query->orWhere('status_quotation', 'non_kontrak');
        });
    }
}
