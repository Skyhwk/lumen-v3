<?php
namespace App\Models;
use App\Models\Sector;

class RequestQuotationKontrakHView extends Sector
{
    protected $table = "view_request_quotation_kontrak_H";
    public $timestamps = false;

    public function addby()
    {
        return $this->belongsTo(MasterKaryawan::class, 'sales_id', 'id');
    }
    public function delby()
    {
        return $this->belongsTo(MasterKaryawan::class, 'del_by', 'id');
    }

    public function emailby()
    {
        return $this->belongsTo(MasterKaryawan::class, 'email_by', 'id');
    }

    public function generateby()
    {
        return $this->belongsTo(MasterKaryawan::class, 'generate_by', 'id');
    }

    public function approveby()
    {
        return $this->belongsTo(MasterKaryawan::class, 'approve_by', 'id');
    }

    public function updateby()
    {
        return $this->belongsTo(MasterKaryawan::class, 'update_by', 'id');
    }

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'id_cabang', 'id');
    }

    // public function samplingKontrak ()
    // {
    //     return $this->hasMany('App\Models\SamplingPlan','qoutation_id', 'id')
    //     ->where('status_quotation','kontrak');
    // }

    public function linkNonKontrak()
    {
        return $this->belongsTo(GenerateLink::class, 'id', 'id_quotation')
            ->where('quotation_status', 'non_kontrak');
    }

    public function linkKontrak()
    {
        return $this->belongsTo(GenerateLink::class, 'id', 'id_quotation')
            ->where('quotation_status', 'kontrak');
    }

    public function orderlink()
    {
        return $this->belongsTo(OrderHeader::class, 'no_document', 'no_document')->where('is_active', 1);
    }

    /* update lanjutan 22/04/2024  */
    public function samplingKontrak()
    {
        return $this->hasMany(SamplingPlan::class, 'qoutation_id', 'id')
            ->where('status_quotation', 'kontrak')->where('is_active', true);
    }
    /* penutup update lanjutan 22/04/2024  */

}
