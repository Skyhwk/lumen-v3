<?php
namespace App\Models;

use App\Models\Sector;

class RequestQuotationView extends Sector
{
    protected $table = "view_request_quotation_non";
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

    public function samplingNonKontrak()
    {
        return $this->hasMany(SamplingPlan::class, 'qoutation_id', 'id')
            ->whereNull('status_quotation');
    }

    // public function linkNonKontrak()
    // {
    //     // return $this->belongsTo('App\Models\GenerateLink','id','id_quotation');
    //     return $this->belongsTo('App\Models\GenerateLink', 'id', 'id_quotation')
    //         ->where('quotation_status', 'non_kontrak');
    // }

    // public function linkKontrak()
    // {
    //     // return $this->belongsTo('App\Models\GenerateLink','id','id_quotation');
    //     return $this->belongsTo('App\Models\GenerateLink', 'id', 'id_quotation')
    //         ->where('quotation_status', 'kontrak');
    // }

    // public function orderlink()
    // {
    //     return $this->belongsTo('App\Models\OrderH', 'no_document', 'no_document')->where('active', 0);
    // }

}
