<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class BpjsKesehatan extends Sector{
    protected $table = 'bpjs_kesehatan';
    protected $guard = [];

    
    protected $fillable = [
        'karyawan',
        'gaji_pokok',
        'potongan_karyawan',
        'nominal_potongan_karyawan',
        'potongan_kantor',
        'nominal_potongan_kantor',
        'no_bpjs_tk',
        'bulan_efektif',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'previous_id',
        'is_active',
    ];

    public $timestamps = false;

    // public function previous()
    // {
    //     return $this->belongsTo(YourModel::class, 'previous_id');
    // }

    // public function karyawan(){
    //     return $this->belongsTo(MasterKaryawan::class, 'karyawan_id', 'id');
    // }
}