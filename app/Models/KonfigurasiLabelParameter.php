<?php

namespace App\Models;

class KonfigurasiLabelParameter extends Sector
{
    protected $table = 'konfigurasi_label_parameter';

    public $timestamps = false;

    protected $fillable = [
        'id_parameter',
        'nama_parameter',
        'volume',
        'regen',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function parameter()
    {
        return $this->belongsTo(Parameter::class, 'id_parameter', 'id');
    }
}
