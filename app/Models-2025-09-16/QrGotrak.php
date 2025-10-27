<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;


class QrGotrak extends Sector
{
    protected $table = "qr_gotrak";
    protected $guarded = [];
    public $timestamps = false;

    
}
