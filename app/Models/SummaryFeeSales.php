<?php

namespace App\Models;

use App\Models\Sector;

class SummaryFeeSales extends Sector
{
    protected $table = 'summary_fee_sales';
    protected $guarded = ['id'];
    public $timestamps = false;

    protected $casts = [
        'januari' => 'array',
        'februari' => 'array',
        'maret' => 'array',
        'april' => 'array',
        'mei' => 'array',
        'juni' => 'array',
        'juli' => 'array',
        'agustus' => 'array',
        'september' => 'array',
        'oktober' => 'array',
        'november' => 'array',
        'desember' => 'array',
    ];

    public function sales()
    {
        return $this->belongsTo(MasterKaryawan::class, 'sales_id')->where('is_active', true);
    }
}
