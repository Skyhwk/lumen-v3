<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PengesahanStps extends Sector{
    protected $table = 'pengesahan_stps';
    
    protected $guarded = [];

    public $timestamps = false;
}