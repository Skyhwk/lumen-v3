<?php

namespace App\Models;

use App\Models\Sector;

class Qsd extends Sector
{
    protected $table = "qsd";
    public $timestamps = false;

    protected $guarded = [];

    public function detail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel');
    }

    public function OrderHeader()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel');
    }
    public function document()
    {
        return $this->hasOne(UploadQsd::class, 'id_qsd', 'id');
    }


}