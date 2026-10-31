<?php

namespace App\Models;

class PerhitunganModalParameterKomponen extends Sector
{
    protected $table = 'master_perhitungan_modal_parameter_komponen';

    protected $fillable = [
        'id_perhitungan_modal',
        'label',
        'harga',
        'urutan',
        'created_at',
    ];

    public $timestamps = false;

    public function header()
    {
        return $this->belongsTo(PerhitunganModalParameter::class, 'id_perhitungan_modal', 'id');
    }
}
