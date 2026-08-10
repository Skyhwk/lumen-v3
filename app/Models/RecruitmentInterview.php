<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentInterview extends Model
{
    protected $table = 'recruitment_interviews';
    protected $guarded = [];

    protected $casts = [
        'tgl_interview' => 'datetime',
        'nilai_interview' => 'float',
    ];

    public function applicant()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }
}
