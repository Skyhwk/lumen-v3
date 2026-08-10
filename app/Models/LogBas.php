<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogBas extends Model
{
    protected $table = 'log_bas';
    
    protected $fillable = [
        'periode',
        'no_quotation',
        'no_order',
        'sales_penanggung_jawab',
        'tanggal_tugas',
        'durasi',
        'sampler',
        'kategori',
        'admin_jadwal',
        'tanggal_dijadwalkan',
        'admin_persiapan',
        'tanggal_persiapan',
        'no_persiapan',
        'filename_persiapan',
        'no_stps',
        'filename_stps',
        'no_cs',
        'filename_cs',
        'no_bas',
        'filename_bas',
        'data_bas',
        'no_sampel'
    ];

    protected $casts = [
        'data_bas' => 'array',
        'kategori' => 'array',
        'no_sampel' => 'array',
    ];
}
