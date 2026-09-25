<?php

namespace App\Models;

class PmTaskActivity extends Sector
{
    protected $table = 'pm_task_activities';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'meta' => 'array',
    ];

    public function task()
    {
        return $this->belongsTo(PmTask::class, 'task_id');
    }
}
