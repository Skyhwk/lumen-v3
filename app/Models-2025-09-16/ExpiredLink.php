<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;


class ExpiredLink extends Sector
{
    protected $table = "expired_link_quotation";
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
