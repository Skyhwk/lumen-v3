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

    public function recruitmentInterviews()
    {
        return $this->hasMany(RecruitmentInterview::class, 'new_recruitment_id');
    }
}
