<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MesinAbsen extends Sector{
    protected $table = 'mesin_absen';
    protected $guard = [];

    
    protected $fillable = [
        'kode_mesin',
        'id_cabang',
        'mode',
        'ipaddress', 
        'status_device', 
        'added_by', 
        'added_at', 
        'updated_by', 
        'updated_at',
        'deleted_by',
        'deleted_at',
        'is_active'
    ];

    public $timestamps = false;

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'id_cabang');
    }
}