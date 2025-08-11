<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;


class QrPsikologi extends Sector
{
    protected $table = "qr_psikologi";
    protected $guarded = [];
    public $timestamps = false;

    
}
