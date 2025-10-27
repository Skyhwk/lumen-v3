<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Sector;

class DataLimbah extends Sector
{
    use HasFactory;

    protected $table = 'data_limbah';

    public function order(){
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel');
    }
}