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
}