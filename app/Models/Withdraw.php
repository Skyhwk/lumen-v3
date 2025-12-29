<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Withdraw extends Sector{
    protected $table = 'withdraw';
    protected $guard = [];


    public $timestamps = false;
    
    public function sales_in_detail()
    {
        return $this->belongsTo(SalesInDetail::class, 'id_sales_in_detail', 'id');
    }
}