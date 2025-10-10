<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\QuotationKontrakH;
class QuotationKontrakD extends Sector
{

    protected $table = 'request_quotation_kontrak_D';
    protected $guarded = [];

    public $timestamps = false;

    public function header()
    {
        return $this->belongsTo(QuotationKontrakH::class, 'id_request_quotation_kontrak_h', 'id');
    }
    public function orderHeader() {
        return $this->header->header(); // bisa shortcut nanti
    }
}
