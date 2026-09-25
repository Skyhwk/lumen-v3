<?php

namespace App\Models;

class PmColumn extends Sector
{
    protected $table = 'pm_columns';
    protected $guarded = [];

    public function project()
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function tasks()
    {
        return $this->hasMany(PmTask::class, 'column_id')
            ->where('is_active', 1)
            ->orderBy('position');
    }
}
