<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PengesahanDokumenSampling extends Sector{
    protected $table = 'pengesahan_dokumen_sampling';
    
    protected $guarded = [];

    public $timestamps = false;
}