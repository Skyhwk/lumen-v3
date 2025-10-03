<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Payroll extends Sector{
    protected $table = 'payroll';
    protected $guard = [];

    
    protected $fillable = [
        'payroll_header_id',
        'karyawan',
        'nik_karyawan',
        'status_karyawan',
        'periode_payroll',
        'hari_kerja',
        'tidak_hadir',
        'gaji_pokok',
        'tunjangan',
        'bonus',
        'pencadangan_upah',
        'jamsostek',
        'bpjs_kesehatan',
        'loan',
        'sanksi',
        'potongan_absen',
        'pajak_pph',
        'no_rekening',
        'nama_bank',
        'take_home_pay',
        'status',
        'keterangan',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'is_active',
    ];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo('App\Models\MasterKaryawan','nik_karyawan', 'nik_karyawan');
    }

    public function department()
    {
        return $this->hasOneThrough(
            'App\Models\MasterDivisi',  // Model tujuan (MasterDivisi)
            'App\Models\MasterKaryawan',        // Model perantara (MasterKaryawan)
            'nik_karyawan',                     // Foreign key di model perantara (MasterKaryawan) ke model ini (Payroll), yaitu 'nik_karyawan'
            'id',                     // Foreign key di model tujuan (MasterDivisi) ke model perantara (MasterKaryawan)
            'nik_karyawan',            // Local key di model ini (Payroll) ke model perantara (MasterKaryawan)
            'id_department'           // Local key di model perantara (MasterKaryawan) ke model tujuan (MasterDivisi)
        )->withDefault();  // Menggunakan withDefault() agar jika tidak ada data, mengembalikan instance kosong dari MasterDivisi
    }

}