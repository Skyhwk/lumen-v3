<?php
namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PengesahanDokumenSampling extends Sector{
    protected $connection = 'lims';

    protected $table = 'pengesahan_dokumen_sampling';
    
    protected $guarded = [];

    public $timestamps = false;
}