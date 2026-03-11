<?php
namespace App\Models;
use App\Models\Sector;

class DistribusiInvoice extends Sector
{
    protected $table = "distribusi_invoice";
    public $timestamps = false;

    protected $guarded = ['id'];
    protected $casts = ['pengiriman' => 'array'];
    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'no_invoice', 'no_invoice');
    }
}