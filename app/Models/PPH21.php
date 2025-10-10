<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PPH21 extends Sector{
    protected $table = 'pph_21';
    
    protected $guarded = [];

    public $timestamps = false;
}