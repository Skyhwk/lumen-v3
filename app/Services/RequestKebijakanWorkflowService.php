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
    ];

    public const KATEGORI_LABELS = [
        'new' => 'Kebijakan Baru',
        'revision' => 'Revisi Kebijakan',
        'termination' => 'Terminasi Kebijakan',
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
}
