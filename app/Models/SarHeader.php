<?php

namespace App\Models;

use App\Models\Sector;
use App\Models\QuotationNonKontrak;

class SarHeader extends Sector
{
    protected $table = "datalapangan_sar_header";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->hasMany(SarDetail::class, 'id_header', 'id')
        ->where('is_active', true);
    }

    public function quotation(){
        return $this->hasOne(QuotationNonKontrak::class, 'no_document', 'no_quotation');
    }
}