<?php

namespace App\Models;

class PmProject extends Sector
{
    protected $table = 'pm_projects';
    protected $guarded = [];

    public function columns()
    {
        return $this->hasMany(PmColumn::class, 'project_id')
            ->where('is_active', 1)
            ->orderBy('position');
    }

    public function tasks()
    {
        return $this->hasMany(PmTask::class, 'project_id')->where('is_active', 1);
    }

    public function division()
    {
        return $this->belongsTo(MasterDivisi::class, 'division_id');
    }
}
