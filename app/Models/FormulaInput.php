<?php

namespace App\Models;

class FormulaInput extends Sector
{
    protected $table = 'formula_inputs';

    public $timestamps = false;

    protected $fillable = [
        'formula_id',
        'variable',
        'label',
        'type',
        'required',
        'default_value',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function formula()
    {
        return $this->belongsTo(Formula::class, 'formula_id', 'id');
    }
}
