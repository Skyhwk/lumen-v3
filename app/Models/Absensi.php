<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Absensi extends Sector{
    protected $table = 'absensi';
    protected $guard = [];
    public $timestamps = false;
    
    // protected $fillable = [
    //     'karyawan_id',
    //     'kode_kartu',
    //     'kode_mesin',
    //     'hari', 
    //     'tanggal', 
    //     'jam', 
    //     'status', 
    // ];

    public function karyawan(){
        return $this->belongsTo(MasterKaryawan::class, 'id');
    }
    public function mesin_absen(){
        return $this->belongsTo(MesinAbsen::class, 'kode_mesin');
    }
    public function rfid(){
        return $this->belongsTo(Rfid::class, 'kode_kartu');
    }
    // public function cabang(){
    //     return $this->belongsTo(MasterCabang::class, '')
    // }
}