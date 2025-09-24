<?php
namespace App\Models;

use App\Models\Sector;

class DraftErgonomiFile extends Sector
{

    protected $table = 'draft_ergonomi_file';

    public $timestamps = false;


    public function order_detail()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')->where('is_active',1);
    }

    public function ergonomi_lapangan()
    {
        return $this->belongsTo(DataLapanganErgonomi::class, 'no_sampel', 'no_sampel');
    }

    public function link ()
    {
        return $this->belongsTo('App\Models\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_ergonomi');
    }

}
