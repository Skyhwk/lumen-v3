<?php
namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\Lims\QuotationKontrakH;

class QuotationKontrakD extends Sector
{

    
    protected $connection = 'lims';
protected $table = 'request_quotation_kontrak_D';
    protected $guarded = [];

    public $timestamps = false;

    public function header()
    {
        return $this->belongsTo(QuotationKontrakH::class, 'id_request_quotation_kontrak_h', 'id');
    }

    // public function orderHeader() {
    //     return $this->header->header(); // bisa shortcut nanti
    // }
}
