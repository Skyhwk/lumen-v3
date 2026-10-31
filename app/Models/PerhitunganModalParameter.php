<?php

namespace App\Models;

class PerhitunganModalParameter extends Sector
{
    protected $table = 'master_perhitungan_modal_parameter';

    protected $fillable = [
        'id_kategori',
        'nama_kategori',
        'id_parameter',
        'nama_parameter',
        'total_modal',
        'tanggal_berlaku',
        'id_hist',
        'catatan',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public $timestamps = false;

    public function komponen()
    {
        return $this->hasMany(PerhitunganModalParameterKomponen::class, 'id_perhitungan_modal', 'id')
            ->orderBy('urutan')
            ->orderBy('id');
    }
}
