<?php

namespace App\Models;

use App\Models\Sector;

class PurchaseOrder extends Sector
{
    protected $table = 'po_customer';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function pelanggan()
    {
        return $this->belongsTo(MasterPelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }
}
