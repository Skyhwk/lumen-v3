<?php

namespace App\Models;

class Formula extends Sector
{
    protected $table = 'formula';

    public $timestamps = false;

    protected $fillable = [
        'id_kategori',
        'id_parameter',
        'id_template_stp',
        'kategori',
        'parameter',
        'template_stp',
        'satuan',
        'formula',
        'formula_json',
        'status',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'formula_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function inputs()
    {
        return $this->hasMany(FormulaInput::class, 'formula_id')
            ->where('is_active', true)
            ->orderBy('urutan');
    }

    public function parameter()
    {
        return $this->belongsTo(Parameter::class, 'id_parameter', 'id');
    }

    public function templateStp()
    {
        return $this->belongsTo(TemplateStp::class, 'id_template_stp', 'id');
    }

    public function verifications()
    {
        return $this->hasMany(FormulaVerification::class, 'formula_id')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('tanggal_verifikasi');
    }

    public function latestVerification()
    {
        return $this->hasOne(FormulaVerification::class, 'formula_id')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('tanggal_verifikasi');
    }
}
