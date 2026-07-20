<?php
namespace App\Models\Lims;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;


class QrPsikologi extends Sector
{
    protected $connection = 'lims';

    protected $table = "qr_psikologi";
    protected $guarded = [];
    public $timestamps = false;

    
}
