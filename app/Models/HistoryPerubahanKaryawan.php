<?php

namespace App\Models;

use App\Models\Sector;

class HistoryPerubahanKaryawan extends Sector
{
    protected $table = 'history_perubahan_karyawan';

    protected $guarded = [];

    protected $casts = [
        'changed_fields' => 'array',
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
    ];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_karyawan', 'id');
    }
}
