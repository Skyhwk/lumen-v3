<?php

namespace App\Models;

class PmTask extends Sector
{
    protected $table = 'pm_tasks';
    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function column()
    {
        return $this->belongsTo(PmColumn::class, 'column_id');
    }

    public function assignee()
    {
        return $this->belongsTo(MasterKaryawan::class, 'assignee_id');
    }

    public function creator()
    {
        return $this->belongsTo(MasterKaryawan::class, 'created_by');
    }

    public function activities()
    {
        return $this->hasMany(PmTaskActivity::class, 'task_id')->orderByDesc('id');
    }
}
