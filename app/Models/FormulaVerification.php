<?php

namespace App\Models;

class FormulaVerification extends Sector
{
    protected $table = 'formula_verification';

    public $timestamps = false;

    protected $fillable = [
        'formula_id',
        'tanggal_verifikasi',
        'no_sampel',
        'hasil_sistem',
        'hasil_manual',
        'rumus_sistem',
        'foto_screenshot',
        'link_dokumen',
        'dokumen_filename',
        'status_verifikasi',
        'status_label',
        'verifikator',
        'catatan',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function formula()
    {
        return $this->belongsTo(Formula::class, 'formula_id', 'id');
    }
}
