<?php

namespace App\Models;

class LimsDocument extends Sector
{
    protected $table = 'lims_documents';
    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'extra_data' => 'array',
        'is_active' => 'boolean',
        'disahkan_pada' => 'date',
    ];
}
