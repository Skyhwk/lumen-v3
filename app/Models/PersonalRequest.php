<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalRequest extends Model
{
    protected $table = 'personnel_requests';

    protected $fillable = [
        'no_request',
        'request_type',
        'karyawan_lama_nama',
        'karyawan_lama_nik',
        'alasan_replacement',
        'alasan_replacement_lainnya',
        'divisi',
        'posisi',
        'jumlah_personal',
        'lokasi_penempatan_cabang',
        'grade_master_karyawan',
        'alasan_kebutuhan',
        'job_description',
        'pendidikan',
        'pengalaman_kerja',
        'usia_maksimum',
        'gender',
        'skill_wajib',
        'sertifikasi',
        'tanggal_dibutuhkan',
        'prioritas',
        'max_salary',
        'created_by',
        'updated_by',
        'is_approve',
        'is_rejected',
        'is_reject',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'divisi_alias',
        'minimum_matching',
        'is_publish',
        'publish_by',
        'publish_at',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'tanggal_dibutuhkan' => 'date',
        'max_salary' => 'decimal:2',
        'jumlah_personal' => 'integer',
        'usia_maksimum' => 'integer',
    ];

    public function masterDivisi()
    {
        return $this->belongsTo(MasterDivisi::class, 'divisi', 'id');
    }

    public function masterJabatan()
    {
        return $this->belongsTo(MasterJabatan::class, 'posisi', 'id');
    }

    public function masterCabang()
    {
        return $this->belongsTo(MasterCabang::class, 'lokasi_penempatan_cabang', 'id');
    }
}
