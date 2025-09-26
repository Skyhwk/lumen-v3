<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PengesahanLhp extends Sector{
    protected $table = 'pengesahan_lhp';
    
    protected $guarded = [];

    public $timestamps = false;
}