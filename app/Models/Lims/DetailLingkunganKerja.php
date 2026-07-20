<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DetailLingkunganKerja extends Sector
{
    
    protected $connection = 'lims';
protected $table = "detail_lingkungan_kerja";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }
}