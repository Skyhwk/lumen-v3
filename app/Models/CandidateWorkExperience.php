<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateWorkExperience extends Model
{
    protected $table = 'candidate_work_experiences';
    protected $guarded = [];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
        'is_active'       => 'boolean',
    ];

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }
}
