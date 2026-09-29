<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtsRequesterSalaryApproval extends Model
{
    protected $table = 'ats_requester_salary_approvals';

    protected $guarded = [];

    protected $casts = [
        'user_reference_amount' => 'float',
        'hrd_offer_amount'      => 'float',
        'decided_at'            => 'datetime',
    ];

    public const DECISION_PENDING = 'pending';
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';
    public const DECISION_SUPERSEDED = 'superseded';

    public function newRecruitment()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }

    public function sallaryOffer()
    {
        return $this->belongsTo(SallaryOffer::class, 'sallary_offer_id');
    }

    public function personnelRequest()
    {
        return $this->belongsTo(PersonnelRequest::class, 'personnel_request_id');
    }

    public function scopePending($query)
    {
        return $query->where('decision', self::DECISION_PENDING);
    }
}
