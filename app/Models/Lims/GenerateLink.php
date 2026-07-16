<?php
namespace App\Models\Lims;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;


class GenerateLink extends Sector
{
    protected $connection = 'lims';

    protected $table = "generate_link_quotation";
    public $timestamps = false;

    public function kontrakH()
    {
        return $this->hasMany(QuotationKontrakH::class, 'id_token', 'id');
    }
    public function nonKontrak()
    {
        return $this->hasMany(QuotationNonKontrak::class, 'id_token', 'id');
    }
}
