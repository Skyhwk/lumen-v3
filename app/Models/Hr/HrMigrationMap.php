<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrMigrationMap extends Model
{
    protected $table = 'hr_migration_map';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'migrated_at' => 'datetime',
    ];
}
