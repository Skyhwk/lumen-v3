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
        'is_active' => 'boolean',
    ];

    protected $appends = ['catatan'];

    public function getCatatanAttribute()
    {
        if (array_key_exists('catatan', $this->attributes) && $this->attributes['catatan'] !== null && $this->attributes['catatan'] !== '') {
            return $this->attributes['catatan'];
        }
        return $this->attributes['catatan_interview'] ?? null;
    }

    public function applicant()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }
}
