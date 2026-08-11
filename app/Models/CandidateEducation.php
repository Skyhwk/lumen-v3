<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateEducation extends Model
{
    protected $table = 'candidate_educations';
    protected $guarded = [];

    protected $casts = [
        'nilai_ipk'  => 'float',
        'is_active'  => 'boolean',
        'tahun_masuk'=> 'integer',
        'tahun_lulus'=> 'integer',
    ];

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }
}
