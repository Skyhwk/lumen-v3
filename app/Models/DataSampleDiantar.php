<?php

namespace App\Models;

use App\Models\Sector;

class DataSampleDiantar extends Sector
{
    protected $table = "data_sample_diantar";
    protected $guarded = ['id'];
    public $timestamps = false;

    public function po()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sample', 'no_sample');
    }
}
