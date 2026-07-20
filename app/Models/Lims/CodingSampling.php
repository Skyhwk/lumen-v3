<?php

namespace App\Models\Lims;

use App\Models\Sector;

class CodingSampling extends Sector
{
    protected $connection = 'lims';

    protected $table = "coding_sampling";

    // public function orderDetail()
    // {
    //     return $this->belongsTo('App\Models\OrderD', 'id', 'id_order_detail');
    // }
}
