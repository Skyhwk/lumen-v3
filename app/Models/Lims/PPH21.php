<?php
namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PPH21 extends Sector{
    protected $connection = 'lims';

    protected $table = 'pph_21';
    
    protected $guarded = [];

    public $timestamps = false;
}