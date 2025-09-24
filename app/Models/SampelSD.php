<?php

namespace App\Models;

use App\Models\Sector;

class SampelSD extends Sector
{
    protected $table = 'sampel_sd';
    protected $guarded = ['id'];
    public $timestamps = false;

    public function dataSample()
    {
        return $this->belongsTo(DataSampleDiantar::class, 'no_order', 'no_order');
    }

    public function order()
    {
        return $this->belongsTo(OrderHeader::class, 'no_quotation', 'no_document')->where('is_active', 1);
    }
}
