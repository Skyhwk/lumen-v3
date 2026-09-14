<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use App\Models\RequestKebijakan;
use App\Models\RequestKebijakanVerifier;
use App\Services\KaryawanProfileService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RequestKebijakanVerifierService
{
    public const VERIFIER_GRADES = [
        'MANAGER',
        'SENIOR MANAGER',
        'DIRECTOR',
    ];

    public static function getVerifierCandidates(): array
    {
        return MasterKaryawan::query()
            ->with('jabatan')
            ->where('is_active', 1)
            ->whereIn(DB::raw('UPPER(REPLACE(grade, "_", " "))'), self::VERIFIER_GRADES)
            ->orderBy('nama_lengkap')
            ->get()
            ->map(function ($karyawan) {
                return [
                    'id' => $karyawan->id,
                    'nama_lengkap' => $karyawan->nama_lengkap,
                    'jabatan' => KaryawanProfileService::resolveJabatan($karyawan),
                    'grade' => RequestKebijakanWorkflowService::normalizeGrade($karyawan->grade ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    public static function assignVerifiers(RequestKebijakan $record, array $verifierIds, $assignedBy): void
    {
        $verifierIds = array_values(array_unique(array_filter(array_map('intval', $verifierIds))));

        if (empty($verifierIds)) {
            throw new \InvalidArgumentException('Minimal 1 reviewer wajib dipilih');
        }

        if (self::hasVerifiers($record)) {
            throw new \InvalidArgumentException('Reviewer sudah pernah ditetapkan untuk request ini');
        }

        $employees = MasterKaryawan::with('jabatan')
            ->where('is_active', 1)
            ->whereIn('id', $verifierIds)
            ->get()
            ->keyBy('id');

        foreach ($verifierIds as $verifierId) {
            if (!$employees->has($verifierId)) {
                throw new \InvalidArgumentException('Reviewer tidak valid');
            }

            $grade = RequestKebijakanWorkflowService::normalizeGrade($employees[$verifierId]->grade ?? '');

            if (!in_array($grade, self::VERIFIER_GRADES, true)) {
                throw new \InvalidArgumentException('Reviewer harus bergrade Manager, Senior Manager, atau Director');
            }
        }

        $now = Carbon::now();
        $assignedByName = $assignedBy->nama_lengkap ?? 'Executive';

        foreach ($verifierIds as $verifierId) {
            $employee = $employees[$verifierId];

            RequestKebijakanVerifier::create([
                'request_kebijakan_id' => $record->id,
                'verifier_karyawan_id' => $employee->id,
                'verifier_nama_lengkap' => $employee->nama_lengkap,
                'verifier_jabatan' => KaryawanProfileService::resolveJabatan($employee),
                'assigned_by' => $assignedByName,
                'assigned_at' => $now,
                'status' => 'pending',
                'verification_round' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $record->update([
            'status' => 'pending_user_review',
            'forwarded_to_user_by' => $assignedByName,
            'forwarded_to_user_at' => $now,
            'user_review_rejected_by' => null,
            'user_review_rejected_at' => null,
            'user_review_rejected_note' => null,
        ]);

        RequestKebijakanNotificationService::reviewAssignedToVerifiers(
            $record->fresh(),
            $assignedBy,
            $verifierIds
        );
    }

    public static function verify(RequestKebijakan $record, $employee, ?string $verificationDateInput): void
    {
        $verifier = self::getPendingVerifierRow($record, $employee);
        $now = Carbon::now();

        try {
            $verificationDate = $verificationDateInput
                ? Carbon::parse($verificationDateInput)->format('Y-m-d')
                : $now->format('Y-m-d');
        } catch (\Throwable $th) {
            $verificationDate = $now->format('Y-m-d');
        }

        $verifier->update([
            'status' => 'verified',
            'verification_date' => $verificationDate,
            'verified_at' => $now,
            'rejection_note' => null,
            'rejected_at' => null,
            'verifier_jabatan' => $verifier->verifier_jabatan ?: KaryawanProfileService::resolveJabatan($employee),
            'updated_at' => $now,
        ]);

        self::evaluateAggregateStatus($record->fresh());
    }

    public static function reject(RequestKebijakan $record, $employee, string $reason): void
    {
        $verifier = self::getPendingVerifierRow($record, $employee);
        $now = Carbon::now();

        $verifier->update([
            'status' => 'rejected',
            'rejected_at' => $now,
            'rejection_note' => $reason,
            'updated_at' => $now,
        ]);

        self::evaluateAggregateStatus($record->fresh());
    }

    public static function evaluateAggregateStatus(RequestKebijakan $record): void
    {
        $verifiers = RequestKebijakanVerifier::query()
            ->where('request_kebijakan_id', $record->id)
            ->where('is_active', true)
            ->get();

        if ($verifiers->isEmpty()) {
            return;
        }

        if ($verifiers->contains(fn ($row) => $row->status === 'pending')) {
            if ($record->status !== 'pending_user_review') {
                $record->update(['status' => 'pending_user_review']);
            }

            return;
        }

        $rejected = $verifiers->where('status', 'rejected');

        if ($rejected->isNotEmpty()) {
            self::syncLegacyRejectFields($record, $rejected);
            $record->update(['status' => 'pending_user_reject_review']);
            RequestKebijakanNotificationService::verifierReviewRejectedPendingApproval($record->fresh(), $rejected);

            return;
        }

        if ($verifiers->every(fn ($row) => $row->status === 'verified')) {
            $names = $verifiers->pluck('verifier_nama_lengkap')->implode(', ');

            $record->update([
                'status' => 'pending_legal_final',
                'user_reviewed_by' => $names,
                'user_reviewed_at' => Carbon::now(),
                'user_review_rejected_by' => null,
                'user_review_rejected_at' => null,
                'user_review_rejected_note' => null,
            ]);

            RequestKebijakanNotificationService::allVerifiersCompletedPendingLegalFinal($record->fresh());
        }
    }

    public static function reopenRejectedAfterLegalResubmit(RequestKebijakan $record): bool
    {
        $rejected = RequestKebijakanVerifier::query()
            ->where('request_kebijakan_id', $record->id)
            ->where('is_active', true)
            ->where('status', 'rejected')
            ->get();

        if ($rejected->isEmpty()) {
            return false;
        }

        $now = Carbon::now();

        foreach ($rejected as $verifier) {
            $verifier->update([
                'status' => 'pending',
                'previous_rejection_note' => $verifier->rejection_note,
                'rejection_note' => null,
                'rejected_at' => null,
                'verification_round' => ((int) $verifier->verification_round) + 1,
                'updated_at' => $now,
            ]);
        }

        RequestKebijakanNotificationService::verifierReopenedAfterResubmit(
            $record->fresh(),
            $rejected->pluck('verifier_karyawan_id')->all()
        );

        return true;
    }

    public static function hasVerifiers(RequestKebijakan $record): bool
    {
        return RequestKebijakanVerifier::query()
            ->where('request_kebijakan_id', $record->id)
            ->where('is_active', true)
            ->exists();
    }

    public static function hasPendingVerifiers(RequestKebijakan $record): bool
    {
        return RequestKebijakanVerifier::query()
            ->where('request_kebijakan_id', $record->id)
            ->where('is_active', true)
            ->where('status', 'pending')
            ->exists();
    }

    public static function getVerificationProgress(RequestKebijakan $record): array
    {
        $verifiers = RequestKebijakanVerifier::query()
            ->where('request_kebijakan_id', $record->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $verified = $verifiers->where('status', 'verified')->count();
        $rejected = $verifiers->where('status', 'rejected')->count();
        $pending = $verifiers->where('status', 'pending')->count();
        $total = $verifiers->count();

        return [
            'total' => $total,
            'verified' => $verified,
            'rejected' => $rejected,
            'pending' => $pending,
            'summary' => $total > 0
                ? trim("{$verified}/{$total} verifikasi" . ($pending ? ", {$pending} pending" : '') . ($rejected ? ", {$rejected} tolak" : ''))
                : '-',
            'items' => $verifiers->map(fn ($row) => [
                'id' => $row->id,
                'verifier_nama_lengkap' => $row->verifier_nama_lengkap,
                'verifier_jabatan' => $row->verifier_jabatan,
                'status' => $row->status,
                'verification_date' => $row->verification_date,
                'verified_at' => $row->verified_at,
                'rejected_at' => $row->rejected_at,
                'rejection_note' => $row->rejection_note,
                'previous_rejection_note' => $row->previous_rejection_note,
                'verification_round' => $row->verification_round,
            ])->values()->all(),
        ];
    }

    public static function canVerifierAct(RequestKebijakan $record, $employee): bool
    {
        return $record->status === 'pending_user_review'
            && $record->is_active
            && RequestKebijakanVerifier::query()
                ->where('request_kebijakan_id', $record->id)
                ->where('verifier_karyawan_id', $employee->id)
                ->where('is_active', true)
                ->where('status', 'pending')
                ->exists();
    }

    public static function isAssignedVerifier(RequestKebijakan $record, $employee): bool
    {
        return RequestKebijakanVerifier::query()
            ->where('request_kebijakan_id', $record->id)
            ->where('verifier_karyawan_id', $employee->id)
            ->where('is_active', true)
            ->exists();
    }

    public static function buildUserReviewScopeQuery($query, $employee)
    {
        return $query
            ->where('is_active', true)
            ->where('status', 'pending_user_review')
            ->where(function ($sub) use ($employee) {
                $sub->whereHas('verifiers', function ($verifierQuery) use ($employee) {
                    $verifierQuery->where('is_active', true)
                        ->where('verifier_karyawan_id', $employee->id)
                        ->where('status', 'pending');
                })->orWhere(function ($legacyQuery) use ($employee) {
                    $legacyQuery->whereDoesntHave('verifiers', function ($verifierQuery) {
                        $verifierQuery->where('is_active', true);
                    })->where('request_by', $employee->nama_lengkap);
                });
            })
            ->orderByDesc('forwarded_to_user_at');
    }

    public static function countUserReviewForEmployee($employee): int
    {
        return RequestKebijakan::query()
            ->where('is_active', true)
            ->where('status', 'pending_user_review')
            ->where(function ($sub) use ($employee) {
                $sub->whereHas('verifiers', function ($verifierQuery) use ($employee) {
                    $verifierQuery->where('is_active', true)
                        ->where('verifier_karyawan_id', $employee->id)
                        ->where('status', 'pending');
                })->orWhere(function ($legacyQuery) use ($employee) {
                    $legacyQuery->whereDoesntHave('verifiers', function ($verifierQuery) {
                        $verifierQuery->where('is_active', true);
                    })->where('request_by', $employee->nama_lengkap);
                });
            })
            ->count();
    }

    private static function getPendingVerifierRow(RequestKebijakan $record, $employee): RequestKebijakanVerifier
    {
        $verifier = RequestKebijakanVerifier::query()
            ->where('request_kebijakan_id', $record->id)
            ->where('verifier_karyawan_id', $employee->id)
            ->where('is_active', true)
            ->where('status', 'pending')
            ->first();

        if (!$verifier) {
            throw new \RuntimeException('Anda tidak memiliki tugas verifikasi pending pada request ini');
        }

        return $verifier;
    }

    private static function syncLegacyRejectFields(RequestKebijakan $record, $rejectedVerifiers): void
    {
        $first = $rejectedVerifiers->sortByDesc('rejected_at')->first();
        $combinedNote = $rejectedVerifiers->map(function ($row) {
            $name = e($row->verifier_nama_lengkap ?? '-');

            return "<p><strong>{$name}</strong></p>" . ($row->rejection_note ?? '');
        })->implode('<hr/>');

        $record->update([
            'user_review_rejected_by' => $first->verifier_nama_lengkap,
            'user_review_rejected_at' => $first->rejected_at,
            'user_review_rejected_note' => $combinedNote,
        ]);
    }
}
