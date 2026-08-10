<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentInterview extends Model
{
    protected $table = 'recruitment_interviews';
    
    // As requested: id, new_recruitment_id, stage, tgl_interview, jenis_interview, link_gmeet, ruangan_interview, status_result, catatan_interview, nilai_interview, interviewer_by, created_by, updated_by, is_active
    protected $guarded = [];

    protected $casts = [
        'tgl_interview' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function newRecruitment()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }
}
