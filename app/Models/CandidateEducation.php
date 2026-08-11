<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateEducation extends Model
{
    protected $table = 'candidate_educations';
    protected $guarded = [];

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }

    public function newRecruitment()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }
}
