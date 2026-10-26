<?php
namespace App\Models;
use App\Models\Sector;

class AllQuote extends Sector
{
    protected $table = 'all_quot';
    public $timestamps = false;
    protected $guarded = [];

    public function orderHeader()
    {
        return $this->belongsTo(OrderHeader::class, 'no_document', 'no_document');
    }

    public function lhpManuals()
    {
        return $this->hasMany(LhpManual::class, 'no_quotation', 'no_document')
            ->where('is_active', true)
            ->whereNull('deleted_at');
    }
}