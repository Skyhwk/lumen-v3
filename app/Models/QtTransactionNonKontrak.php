<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class QtTransactionNonKontrak extends Sector
{
    protected $table = 'qt_transaction_non_kontrak';
    public $timestamps = false;

    public function quotation(){
        return $this->belongsTo(QuotationNonKontrak::class, 'no_qt', 'no_document');
    }

    public function sales(){
        return $this->belongsTo(MasterKaryawan::class, 'sales_id', 'id');
    }
}
