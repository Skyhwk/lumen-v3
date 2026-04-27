<?php

namespace App\Models;

use App\Models\Sector;

class PengajuanFeeSamplingDetail extends Sector
{
    protected $table = 'pengajuan_fee_sampling_detail';

    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'rincian_fee_pokok' => 'array',
        'fee_tambahan_rincian' => 'array',
    ];

    public function pengajuan(){
        return $this->belongsTo(PengajuanFeeSampling::class, 'pengajuan_fee_sampling_id');
    }
}