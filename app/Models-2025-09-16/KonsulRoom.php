<?php
namespace App\Models;
use App\Models\Sector;

class KonsulRoom extends Sector
{
    protected $table = "consule_room";
    public $timestamps = false;
    protected $fillable = [
        "consule_id",
        "user_id",
        "hrd_id",
        "consule_id",
        "keluhan",
        "solusi",
        "resume",
        "status"
    ];
    protected $connection = 'android_intilab'; // Sesuai nama koneksi di config/database.php

    public function konsul()
    {
        return $this->belongsTo(Konsul::class, 'consule_id', 'id');
    }

}