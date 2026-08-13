<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateProfile extends Model
{
    protected $table = 'candidate_profiles';
    protected $guarded = [];

    public function newRecruitment()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }

    public function educations()
    {
        return $this->hasMany(CandidateEducation::class, 'candidate_profile_id');
    }

    public function workExperiences()
    {
        return $this->hasMany(CandidateWorkExperience::class, 'candidate_profile_id');
    }
}
