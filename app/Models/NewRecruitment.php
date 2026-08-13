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
        return $this->belongsTo(PersonnelRequest::class, 'personnel_request_id');
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

    public function sallaryOffer()
    {
        return $this->hasOne(SallaryOffer::class, 'new_recruitment_id');
    }

    public function salaryOffer()
    {
        return $this->hasOne(SallaryOffer::class, 'new_recruitment_id');
    }

    public function candidateDataOffer()
    {
        return $this->hasOne(CandidateDataOffers::class, 'new_recruitment_id');
    }

    public function masterJabatan()
    {
        return $this->belongsTo(MasterJabatan::class, 'bagian_di_lamar', 'id');
    }
    public function candidateProfile()
    {
        return $this->hasOne(CandidateProfile::class, 'new_recruitment_id');
    }

    public function candidateEducations()
    {
        return $this->hasMany(CandidateEducation::class, 'new_recruitment_id');
    }

    public function candidateWorkExperiences()
    {
        return $this->hasMany(CandidateWorkExperience::class, 'new_recruitment_id');
    }

    public function candidateMedicalInformation()
    {
        return $this->hasOne(CandidateMedicalInformation::class, 'new_recruitment_id');
    }
}
