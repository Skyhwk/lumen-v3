<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class RecruitmentInterview extends Model
{
    protected $table = 'recruitment_interviews';
    protected $guarded = [];

    protected $casts = [
        'tgl_interview' => 'datetime',
        'nilai_interview' => 'float',
        'is_active' => 'boolean',
    ];

    public function applicant()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }

    public static function hasTanggalJoinColumn(): bool
    {
        return Schema::hasColumn((new static)->getTable(), 'tanggal_join');
    }

    public static function normalizeTanggalJoin($value): ?string
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
