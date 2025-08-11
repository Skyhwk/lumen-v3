<?php
namespace App\Models;
use App\Models\Sector;

class RecruitmantExamp extends Sector
{
    protected $table = "recruitmant_examp";
    public $timestamps = false;

    public function recruitment()
    {
        return $this->belongsTo(Recruitment::class, 'kode_uniq', 'kode_uniq');
    }
}