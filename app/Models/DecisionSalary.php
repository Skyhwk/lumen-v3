<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DecisionSalary extends Model
{
    protected $table = 'decision_salary';

    protected $guarded = [];

    protected $casts = [
        'user_amount'       => 'float',
        'hrd_amount'        => 'float',
        'pencadangan_upah'  => 'float',
        'round'             => 'integer',
        'decided_at'        => 'datetime',
        'allow_hrd_transfer_jabatan' => 'boolean',
        'allow_hrd_transfer_divisi' => 'boolean',
    ];

    public static function hasAllowHrdTransferColumns(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $table = (new static)->getTable();
        $cached = Schema::hasColumn($table, 'allow_hrd_transfer_jabatan')
            && Schema::hasColumn($table, 'allow_hrd_transfer_divisi');

        return $cached;
    }

    public static function hasTanggalJoinColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = Schema::hasColumn((new static)->getTable(), 'tanggal_join');

        return $cached;
    }

    public static function asBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 1 || $value === '1';
    }

    public const DECISION_PENDING = 'pending';
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';
    public const DECISION_NEGOTIATED = 'negotiated';
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
