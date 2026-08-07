<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalRequest extends Model
{
    protected $table = 'personal_requests';

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
    ];

    protected $casts = [
        'tanggal_dibutuhkan' => 'date',
        'max_salary' => 'decimal:2',
        'jumlah_personal' => 'integer',
        'usia_maksimum' => 'integer',
    ];
}
