<?php
namespace App\Models;
use App\Models\Sector;

class AllQTActive extends Sector
{
    protected $table = 'all_qt_active';
    public $timestamps = false;
    protected $guarded = [];

    public function qsd()
    {
        return $this->hasOne(Qsd::class, 'order_header_id', 'id')->with('document');
    }

    public function getInvoice()
    {
        return $this->hasMany(Invoice::class, 'no_quotation', 'no_document')->where('is_active', true);
    }
}