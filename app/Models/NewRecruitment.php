<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewRecruitment extends Model
{
    protected $table = 'new_recruitment';
    protected $guarded = [];

    protected $casts = [
        'pendidikan' => 'array',
        'pengalaman_kerja' => 'array',
        'tanggal_join_tercepat' => 'date',
    ];

    public function personnelRequest()
    {
        return $this->belongsTo(PersonnelRequest::class, 'personnel_request_id');
    }

    public function personalRequest()
    {
        return $this->belongsTo(PersonalRequest::class, 'personnel_request_id');
    }

    public function interviews()
    {
        return $this->hasMany(RecruitmentInterview::class, 'new_recruitment_id');
    }

    public function hrdInterview()
    {
        return $this->hasOne(RecruitmentInterview::class, 'new_recruitment_id')
            ->where('stage', 'hrd')
            ->where('is_active', 1)
            ->orderBy('id', 'desc');
    }

    public function hrdInterviewHistories()
    {
        return $this->hasMany(RecruitmentInterview::class, 'new_recruitment_id')
            ->where('stage', 'hrd')
            ->orderBy('id', 'desc');
    }

    public function userInterview()
    {
        return $this->hasOne(RecruitmentInterview::class, 'new_recruitment_id')
            ->where('stage', 'user')
            ->where('is_active', 1)
            ->orderBy('id', 'desc');
    }

    public function userInterviewHistories()
    {
        return $this->hasMany(RecruitmentInterview::class, 'new_recruitment_id')
            ->where('stage', 'user')
            ->orderBy('id', 'desc');
    }

    public function salaryOffer()
    {
        return $this->hasOne(SalaryOffer::class, 'new_recruitment_id')->orderBy('id', 'desc');
    }
}
