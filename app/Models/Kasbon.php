<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Kasbon extends Sector{
    protected $table = 'kasbon';
    protected $guard = [];
    protected $casts = [
        'custom_potongan' => 'array',
        'history_perubahan' => 'array',
    ];
    
    protected $fillable = [
        'karyawan',
        'nik_karyawan',
        'id_karyawan',
        'total_kasbon',
        'tenor',
        'interval_pemotongan_bulan',
        'bulan_mulai_pemotongan',
        'mode_potongan',
        'nominal_potongan',
        'custom_potongan',
        'tanggal_permintaan',
        'tanggal_pencairan',
        'keterangan',
        'sisa_tenor',
        'sisa_kasbon',
        'status',
        'history_perubahan',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'kode_kasbon',
        'previous_id',
        'is_active',
    ];

    public $timestamps = false;

    public function masterKaryawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_karyawan', 'id');
    }
}
