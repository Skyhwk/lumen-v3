<?php
namespace App\Models;

use App\Models\Sector;

class JadwalLibur extends Sector
{
    protected $table = 'jadwal_libur';
    protected $fillable = [
        'judul', 
        'deskripsi', 
        'start_date', 
        'end_date', 
        'created_at', 
        'created_by', 

    ];

    public $timestamps = false;

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function karyawan() 
    {
        return $this->hasOne(MasterKaryawan::class, 'user_id', 'user_id');
    }
}