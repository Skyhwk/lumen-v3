<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterSallary extends Sector{
    protected $table = 'master_sallary';
    protected $guard = [];

    
    protected $fillable = [
        'karyawan',
        'nik_karyawan',
        'id_karyawan',
        'gaji_pokok',
        'tunjangan_kerja',
        'jadwal_pembayaran_thr',
        'metode_pembayaran_gaji',
        'jumlah_hari_kerja',
        'jadwal_penggajian',
        'satuan_jangka_waktu',
        'jumlah_jangka_waktu',
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

    public function masterKaryawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_karyawan', 'id');
    }
}