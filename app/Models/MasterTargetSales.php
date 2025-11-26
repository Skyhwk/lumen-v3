<?php

namespace App\Models;

use App\Models\Sector;


class MasterTargetSales extends Sector
{
    protected $table = 'master_target_sales';
    protected $guarded = [];
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
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id')->where('is_active', true);
    }
}