<?php

namespace App\Models;

class PmTaskComment extends Sector
{
    protected $table = 'pm_task_comments';
    protected $guarded = [];

    public function task()
    {
        return $this->belongsTo(PmTask::class, 'task_id');
    }

    public function author()
    {
        return $this->belongsTo(MasterKaryawan::class, 'user_id');
    }
}
