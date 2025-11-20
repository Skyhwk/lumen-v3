<?php

namespace App\Models;

use App\Models\Sector;

class DokumenBap extends Sector
{
    protected $table = 'dokumen_bap';
    public $timestamps = false;
    protected $guarded = [];

    public function order(){
        return $this->belongsTo(OrderHeader::class, 'id_order', 'id');
    }
}