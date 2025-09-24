<?php
namespace App\Models;
use App\Models\Sector;

class Konsul extends Sector
{
    protected $table = "consule";
    public $timestamps = false;


    protected $guarded = [];
    protected $connection = 'android_intilab'; // Sesuai nama koneksi di config/database.php

    public function konsulRoom()
    {
        return $this->hasMany(KonsulRoom::class, 'consule_id', 'id');
    }

    public function user()
    {
        return $this->hasOne(MasterKaryawan::class, 'id', 'user_id')
            ->select(['id', 'nama_lengkap', 'id_department']);
    }

}