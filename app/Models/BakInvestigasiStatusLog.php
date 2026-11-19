<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BakInvestigasiStatusLog extends Model
{
    public $timestamps = false;

    protected $table = 'bak_investigasi_status_logs';

    protected $fillable = [
        'bak_investigasi_id',
        'from_status',
        'to_status',
        'action',
        'actor_id',
        'actor_name',
        'notes',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}