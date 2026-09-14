<?php

namespace App\Services;

use App\Models\RequestKebijakan;

class RequestKebijakanWorkflowService
{
    public const STATUS_LABELS = [
        'waiting_approval' => 'Menunggu Persetujuan',
        'approved' => 'Disetujui',
        'on_process' => 'Dalam Proses',
        'rejected' => 'Ditolak',
        'completed' => 'Selesai',
        'pending_review' => 'Menunggu Review',
        'pending_user_review' => 'Menunggu Verifikasi',
        'pending_user_reject_review' => 'Penolakan Verifikasi',
        'pending_legal_final' => 'Menunggu Verifikasi Final Legal',
        'pending_director_approval' => 'Menunggu Pengesahan Director',
        'needs_revision' => 'Perlu Revisi',
        'needs_revision_executive' => 'Perlu Revisi (Manager)',
        'needs_revision_user' => 'Perlu Revisi (Penolakan User)',
    ];

    public const REVIEW_REJECT_SOURCE_LABELS = [
        'executive' => 'Ditolak Manager',
        'executive_after_user' => 'Ditolak Manager (Setelah User)',
    ];

    public const KATEGORI_LABELS = [
        'new' => 'Ketetapan Baru',
        'revision' => 'Revisi Ketetapan',
        'termination' => 'Terminasi Ketetapan',
    ];

    public const APPROVER_GRADES = [
        'MANAGER',
        'SENIOR MANAGER',
        'EXECUTIVE',
        'DIRECTOR',
    ];

    public static function normalizeGrade(?string $grade): string
    {
        $normalized = strtoupper(trim((string) $grade));

        return str_replace('_', ' ', $normalized);
    }

    public static function canApprove($employee): bool
    {
        return in_array(self::normalizeGrade($employee->grade ?? ''), self::APPROVER_GRADES, true);
    }

    public static function resolveDisplayStatus(RequestKebijakan $record): string
    {
        if (!$record->is_active && $record->deleted_at) {
            return 'Void - Pemohon';
        }

        if ($record->status === 'pending_user_review') {
            return self::STATUS_LABELS['pending_user_review'];
        }

        if ($record->status === 'pending_user_reject_review') {
            return self::STATUS_LABELS['pending_user_reject_review'];
        }

        if (
            $record->status === 'completed'
            && optional($record->drafting)->status === 'submitted'
            && self::canExecutiveReviewSubmittedDraft($record)
        ) {
            return self::STATUS_LABELS['pending_review'];
        }

        if (
            $record->status === 'on_process'
            && optional($record->drafting)->status === 'in_progress'
            && !empty($record->drafting->review_rejected_note)
        ) {
            return self::resolveRevisionDisplayStatus($record);
        }

        if ($record->status === 'pending_legal_final') {
            return self::STATUS_LABELS['pending_legal_final'];
        }

        if ($record->status === 'pending_director_approval') {
            return self::STATUS_LABELS['pending_director_approval'];
        }

        if (
            $record->status === 'completed'
            && $record->user_reviewed_at
        ) {
            return self::STATUS_LABELS['completed'];
        }

        return self::STATUS_LABELS[$record->status] ?? ucfirst(str_replace('_', ' ', (string) $record->status));
    }

    public static function resolveKategoriLabel(?string $kategori): string
    {
        $key = strtolower(trim((string) $kategori));

        return self::KATEGORI_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key ?: '-'));
    }

    public static function buildPipeline(RequestKebijakan $record): array
    {
        $isRejected = $record->status === 'rejected';
        $isVoid = !$record->is_active && $record->deleted_at;
        $isExecutiveApproved = in_array($record->status, ['approved', 'on_process', 'completed'], true) || $record->approval_at;
        $isOnProcess = $record->status === 'on_process';
        $isCompleted = $record->status === 'completed';

        $steps = [
            [
                'title' => 'Pengajuan',
                'icon' => 'fa-paper-plane',
                'by' => $record->request_by,
                'at' => $record->request_at,
                'done' => !!$record->request_at,
            ],
            [
                'title' => 'Disetujui',
                'icon' => 'fa-check-circle',
                'by' => $isRejected ? $record->rejected_by : $record->approval_by,
                'at' => $isRejected ? $record->rejected_at : $record->approval_at,
                'done' => $isExecutiveApproved && !$isRejected && !$isVoid,
                'rejected' => $isRejected,
                'rejectedBy' => $record->rejected_by,
                'rejectedAt' => $record->rejected_at,
                'rejectionNote' => $record->rejected_note,
                'inProgress' => $record->status === 'waiting_approval' && !$isVoid && !$isRejected,
            ],
            [
                'title' => 'Dalam Proses',
                'icon' => 'fa-cogs',
                'by' => $record->processed_by,
                'at' => $record->processed_at,
                'done' => $isCompleted,
                'inProgress' => $isOnProcess,
            ],
            [
                'title' => 'Selesai',
                'icon' => 'fa-flag-checkered',
                'by' => null,
                'at' => null,
                'done' => $isCompleted,
            ],
        ];

        if ($isVoid) {
            $steps[] = [
                'title' => 'Void',
                'icon' => 'fa-ban',
                'by' => $record->deleted_by,
                'at' => $record->deleted_at,
                'done' => true,
                'rejected' => true,
                'rejectionNote' => 'Request dihapus oleh pemohon',
            ];
        }

        return self::markCurrentPipelineStep($steps);
    }

    private static function markCurrentPipelineStep(array $steps): array
    {
        $currentIndex = null;

        foreach ($steps as $index => $step) {
            if (!empty($step['inProgress'])) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null) {
            foreach ($steps as $index => $step) {
                if (empty($step['done']) && empty($step['rejected'])) {
                    $currentIndex = $index;
                    break;
                }
            }
        }

        return array_map(function ($step, $index) use ($currentIndex) {
            $step['isCurrent'] = $currentIndex !== null && $index === $currentIndex;

            return $step;
        }, $steps, array_keys($steps));
    }

    public static function getApprovalTabCounts(): array
    {
        return [
            'pending' => RequestKebijakan::query()
                ->where('is_active', true)
                ->where('status', 'waiting_approval')
                ->count(),
            'approved_waiting' => RequestKebijakan::query()
                ->where('is_active', true)
                ->where('status', 'approved')
                ->count(),
            'review' => self::buildExecutiveReviewQuery()->count(),
            'waiting_user_review' => RequestKebijakan::query()
                ->where('is_active', true)
                ->where('status', 'pending_user_review')
                ->count(),
            'user_reject_review' => RequestKebijakan::query()
                ->where('is_active', true)
                ->where('status', 'pending_user_reject_review')
                ->count(),
            'legal_final' => RequestKebijakan::query()
                ->where('is_active', true)
                ->where('status', 'pending_legal_final')
                ->count(),
        ];
    }

    public static function getRequesterTabCounts($employee): array
    {
        $scoped = fn ($query) => self::applyRequesterEmployeeScope($query, $employee);

        return [
            'pending' => $scoped(self::buildRequesterPendingQuery())->count(),
            'user_review' => RequestKebijakanVerifierService::countUserReviewForEmployee($employee),
            'completed' => $scoped(self::buildRequesterCompletedQuery())->count(),
            'void' => $scoped(self::buildRequesterVoidQuery())->count(),
        ];
    }

    public static function buildExecutiveReviewQuery()
    {
        return RequestKebijakan::query()
            ->from('request_kebijakan')
            ->where('request_kebijakan.is_active', true)
            ->where('request_kebijakan.status', 'completed')
            ->whereNull('request_kebijakan.user_reviewed_at')
            ->where(function ($query) {
                $query->whereNull('request_kebijakan.forwarded_to_user_at')
                    ->orWhereRaw('EXISTS (
                        SELECT 1 FROM drafting_kebijakan dk_resubmit
                        WHERE dk_resubmit.request_kebijakan_id = request_kebijakan.id
                          AND dk_resubmit.is_active = 1
                          AND dk_resubmit.status = ?
                          AND dk_resubmit.submitted_at > request_kebijakan.forwarded_to_user_at
                    )', ['submitted']);
            })
            ->join('drafting_kebijakan as dk_review', function ($join) {
                $join->on('dk_review.request_kebijakan_id', '=', 'request_kebijakan.id')
                    ->where('dk_review.is_active', true)
                    ->where('dk_review.status', 'submitted');
            })
            ->select('request_kebijakan.*', 'dk_review.submitted_at as draft_submitted_at')
            ->orderByDesc('dk_review.submitted_at');
    }

    public static function buildRequesterPendingQuery()
    {
        return RequestKebijakan::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereIn('status', [
                    'waiting_approval',
                    'approved',
                    'on_process',
                    'pending_user_reject_review',
                    'pending_user_review',
                    'pending_legal_final',
                    'pending_director_approval',
                ])
                    ->orWhere(function ($sub) {
                        $sub->where('status', 'completed')
                            ->whereNull('forwarded_to_user_at')
                            ->whereNull('user_reviewed_at')
                            ->whereHas('drafting', function ($draft) {
                                $draft->where('is_active', true)->where('status', 'submitted');
                            });
                    });
            });
    }

    public static function buildRequesterCompletedQuery()
    {
        return RequestKebijakan::query()
            ->where('is_active', true)
            ->where('status', 'completed')
            ->whereHas('activeKebijakanDokumen');
    }

    public static function buildRequesterVoidQuery()
    {
        return RequestKebijakan::query()->where(function ($q) {
            $q->where('status', 'rejected')
                ->orWhere(function ($sub) {
                    $sub->where('is_active', false)
                        ->whereNotNull('deleted_at');
                });
        });
    }

    public static function applyRequesterEmployeeScope($query, $employee)
    {
        $grade = self::normalizeGrade($employee->grade ?? '');

        if (in_array($grade, ['EXECUTIVE', 'DIRECTOR'], true)) {
            return $query;
        }

        if (in_array($grade, ['MANAGER', 'SENIOR MANAGER'], true)) {
            $creators = \App\Services\GetBawahan::where('id', $employee->id)->get()->pluck('nama_lengkap')->toArray();
            $creators[] = $employee->nama_lengkap;

            return $query->whereIn('request_by', $creators);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canUserReviewRequest(RequestKebijakan $record, $employee): bool
    {
        if (RequestKebijakanVerifierService::hasVerifiers($record)) {
            return RequestKebijakanVerifierService::canVerifierAct($record, $employee);
        }

        return $record->status === 'pending_user_review'
            && $record->is_active
            && $record->request_by === $employee->nama_lengkap;
    }

    public static function canExecutiveReviewSubmittedDraft(RequestKebijakan $record): bool
    {
        if (
            $record->status !== 'completed'
            || !$record->is_active
            || !$record->drafting
            || $record->drafting->status !== 'submitted'
            || $record->user_reviewed_at
        ) {
            return false;
        }

        if (RequestKebijakanVerifierService::hasVerifiers($record)) {
            return false;
        }

        if (!$record->forwarded_to_user_at) {
            return true;
        }

        $draftSubmittedAt = $record->drafting->submitted_at;

        return $draftSubmittedAt
            && $record->forwarded_to_user_at
            && $draftSubmittedAt > $record->forwarded_to_user_at;
    }

    public static function resolveRevisionDisplayStatus(RequestKebijakan $record): string
    {
        $source = optional($record->drafting)->review_rejected_source;

        if ($source === 'executive_after_user') {
            return self::STATUS_LABELS['needs_revision_user'];
        }

        if ($source === 'executive') {
            return self::STATUS_LABELS['needs_revision_executive'];
        }

        return self::STATUS_LABELS['needs_revision'];
    }

    public static function resolveReviewRejectSourceLabel(?string $source): ?string
    {
        if (!$source) {
            return null;
        }

        return self::REVIEW_REJECT_SOURCE_LABELS[$source] ?? ucfirst(str_replace('_', ' ', $source));
    }
}
