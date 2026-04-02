<?php

namespace App\Models;

use App\Models\Sector;

class PengajuanFeeSampling extends Sector
{
    protected $table = 'pengajuan_fee_sampling';

    public $timestamps = false;
    protected $guarded = [];

    public function detail_fee()
    {
        return $this->hasMany(PengajuanFeeSamplingDetail::class, 'pengajuan_fee_sampling_id');
    }
}