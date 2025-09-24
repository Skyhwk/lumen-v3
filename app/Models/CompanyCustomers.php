<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class CompanyCustomer extends Sector
{
    protected $connection = "company_profile";
    protected $table = "customers";
    public $timestamps = false;
    protected $guarded = [];

    // public function cabang() {
    //     return $this->belongsTo(MasterCabang::class, 'id_cabang', 'id');
    // }
}
