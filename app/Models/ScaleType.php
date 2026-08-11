<?php

namespace App\Models;

class ScaleType extends Sector
{
    protected $table = 'scale_types';
    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'is_active' => 'boolean',
    ];

    public function questions()
    {
        return $this->hasMany(Question::class, 'scale_type_id');
    }
}