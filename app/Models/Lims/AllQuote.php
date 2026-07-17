<?php
namespace App\Models\Lims;
use App\Models\Sector;

class AllQuote extends Sector
{
    protected $connection = 'lims';

    protected $table = 'all_quot';
    public $timestamps = false;
    protected $guarded = [];

    public function orderHeader()
    {
        return $this->belongsTo(OrderHeader::class, 'no_document', 'no_document');
    }
}