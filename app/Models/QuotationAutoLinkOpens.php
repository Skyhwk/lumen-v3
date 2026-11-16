<?php

namespace App\Models;

class QuotationAutoLinkOpens extends Sector
{
    protected $table = 'quotation_auto_link_opens';

    protected $guarded = [];

    protected $casts = [
        'quotation_id' => 'integer',
        'promo_id' => 'integer',
        'sales_id' => 'integer',
        'open_count' => 'integer',
        'is_processed' => 'boolean',
        'first_opened_at' => 'datetime',
        'last_opened_at' => 'datetime',
    ];

    public function quotation()
    {
        return $this->belongsTo(QuotationNonKontrak::class, 'quotation_id', 'id');
    }

    public function sales()
    {
        return $this->belongsTo(MasterKaryawan::class, 'sales_id', 'id');
    }
}
