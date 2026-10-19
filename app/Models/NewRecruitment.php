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
        'referensi' => 'array',
        'rekomendasi' => 'array',
        'masalah_hukum' => 'array',
        'bypass' => 'array',
        'is_reject_finance' => 'boolean',
        'is_rejected_kandidat' => 'boolean',
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
        return $this->hasOne(SallaryOffer::class, 'new_recruitment_id')
            ->where('is_active', true)
            ->latest('id');
    }

    public function sallaryOffers()
    {
        return $this->hasMany(SallaryOffer::class, 'new_recruitment_id')
            ->orderByDesc('id');
    }

    public function latestSallaryOffer()
    {
        return $this->hasOne(SallaryOffer::class, 'new_recruitment_id')
            ->latest('id');
    }

    public function salaryOffer()
    {
        return $this->hasOne(SallaryOffer::class, 'new_recruitment_id')
            ->where('is_active', true)
            ->latest('id');
    }

    public function decisionSalaries()
    {
        return $this->hasMany(DecisionSalary::class, 'new_recruitment_id')
            ->orderByDesc('id');
    }

    public function pendingDecisionSalary()
    {
        return $this->hasOne(DecisionSalary::class, 'new_recruitment_id')
            ->where('decision', DecisionSalary::DECISION_PENDING)
            ->latest('id');
    }

    /**
     * Putaran nego aktif di tab User:
     * - pending: menunggu keputusan User
     * - rejected / negotiated: menunggu HRD re-input
     * - approved: tetap tampil sampai HRD kirim offering ke kandidat
     */
    public function openDecisionSalary()
    {
        return $this->hasOne(DecisionSalary::class, 'new_recruitment_id')
            ->whereIn('decision', [
                DecisionSalary::DECISION_PENDING,
                DecisionSalary::DECISION_REJECTED,
                DecisionSalary::DECISION_NEGOTIATED,
                DecisionSalary::DECISION_APPROVED,
            ])
            ->latest('id');
    }

    public function candidateDataOffer()
    {
        return $this->hasOne(CandidateDataOffers::class, 'new_recruitment_id');
    }

    public function masterJabatan()
    {
        return $this->belongsTo(MasterJabatan::class, 'bagian_di_lamar', 'id');
    }

    /**
     * posisi_dilamar sering menyimpan ID master_jabatan (dari personnel request).
     */
    public function appliedPositionJabatan()
    {
        return $this->belongsTo(MasterJabatan::class, 'posisi_dilamar', 'id');
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

    public function getTanggalJoinTercepatAttribute($value)
    {
        return static::normalizeDateOnly($this->attributes['tanggal_join_tercepat'] ?? $value);
    }

    public static function normalizeDateOnly($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_object($value) && isset($value->date)) {
            $value = $value->date;
        }

        if (!preg_match('/(\d{4}-\d{2}-\d{2})/', trim((string) $value), $matches)) {
            return null;
        }

        return $matches[1];
    }
}
