<?php

namespace App\Services;

class SalaryAdjustmentWorkflowService
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_WAITING_RECEIVER = 'waiting_receiver';
    public const STATUS_RECEIVER_RESPONDED = 'receiver_responded';
    public const STATUS_HRD_PROCESSING = 'hrd_processing';
    public const STATUS_WAITING_ASSESSMENT = 'waiting_assessment';
    public const STATUS_ASSESSMENT_IN_PROGRESS = 'assessment_in_progress';
    public const STATUS_ASSESSMENT_COMPLETED = 'assessment_completed';
    public const STATUS_COUNSELING_SCHEDULED = 'counseling_scheduled';
    public const STATUS_COUNSELING_COMPLETED = 'counseling_completed';
    public const STATUS_FINAL_EVALUATION = 'final_evaluation';
    public const STATUS_FINANCE_REVIEW = 'finance_review';
    public const STATUS_FINANCE_RETURNED = 'finance_returned';
    public const STATUS_WAITING_APPROVAL_IBU = 'waiting_approval_ibu';
    public const STATUS_WAITING_APPROVAL_BAPAK = 'waiting_approval_bapak';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';

    public const MANAGER_TAB_WAITING = 'waiting';
    public const MANAGER_TAB_MUTASI_INBOX = 'mutasi_inbox';
    public const MANAGER_TAB_IN_PROGRESS = 'in_progress';
    public const MANAGER_TAB_COMPLETED = 'completed';
    public const MANAGER_TAB_REJECTED = 'rejected';

    public const HRD_TAB_WAITING_PROCESS = 'waiting_process';
    public const HRD_TAB_WAITING_ASSESSMENT = 'waiting_assessment';
    public const HRD_TAB_MONITOR_COUNSELING = 'monitor_counseling';
    public const HRD_TAB_COUNSELING_SCHEDULE = 'counseling_schedule';
    public const HRD_TAB_FINAL_EVALUATION = 'final_evaluation';
    public const HRD_TAB_WAITING_FINANCE = 'waiting_finance';
    public const HRD_TAB_WAITING_APPROVAL_IBU = 'waiting_approval_ibu';
    public const HRD_TAB_WAITING_APPROVAL_BAPAK = 'waiting_approval_bapak';
    public const HRD_TAB_REKAP_COMPLETE = 'rekap_complete';
    public const HRD_TAB_REKAP_REJECTED = 'rekap_rejected';

    public const HRD_TAB_STATUS_MAP = [
        self::HRD_TAB_WAITING_PROCESS => [
            self::STATUS_SUBMITTED,
            self::STATUS_RECEIVER_RESPONDED,
        ],
        self::HRD_TAB_WAITING_ASSESSMENT => [
            self::STATUS_HRD_PROCESSING,
            self::STATUS_WAITING_ASSESSMENT,
            self::STATUS_ASSESSMENT_IN_PROGRESS,
        ],
        self::HRD_TAB_COUNSELING_SCHEDULE => [self::STATUS_ASSESSMENT_COMPLETED],
        self::HRD_TAB_MONITOR_COUNSELING => [
            self::STATUS_ASSESSMENT_COMPLETED,
            self::STATUS_COUNSELING_SCHEDULED,
        ],
        self::HRD_TAB_FINAL_EVALUATION => [
            self::STATUS_FINAL_EVALUATION,
            self::STATUS_FINANCE_RETURNED,
        ],
        self::HRD_TAB_WAITING_FINANCE => [self::STATUS_FINANCE_REVIEW],
        self::HRD_TAB_WAITING_APPROVAL_IBU => [self::STATUS_WAITING_APPROVAL_IBU],
        self::HRD_TAB_WAITING_APPROVAL_BAPAK => [self::STATUS_WAITING_APPROVAL_BAPAK],
        self::HRD_TAB_REKAP_COMPLETE => [self::STATUS_COMPLETED],
        self::HRD_TAB_REKAP_REJECTED => [self::STATUS_REJECTED],
    ];

    public const STATUS_LABELS = [
        self::STATUS_SUBMITTED => 'Menunggu HRD',
        self::STATUS_WAITING_RECEIVER => 'Menunggu Manager Penerima',
        self::STATUS_RECEIVER_RESPONDED => 'Manager Penerima Merespons',
        self::STATUS_HRD_PROCESSING => 'Sedang Diproses HRD',
        self::STATUS_WAITING_ASSESSMENT => 'Menunggu Assessment',
        self::STATUS_ASSESSMENT_IN_PROGRESS => 'Assessment Berjalan',
        self::STATUS_ASSESSMENT_COMPLETED => 'Assessment Selesai',
        self::STATUS_COUNSELING_SCHEDULED => 'Konseling Dijadwalkan',
        self::STATUS_COUNSELING_COMPLETED => 'Konseling Selesai',
        self::STATUS_FINAL_EVALUATION => 'Evaluasi Final',
        self::STATUS_FINANCE_REVIEW => 'Review Finance',
        self::STATUS_FINANCE_RETURNED => 'Banding Finance (HRD)',
        self::STATUS_WAITING_APPROVAL_IBU => 'Waiting Approval',
        self::STATUS_WAITING_APPROVAL_BAPAK => 'Waiting Approval Final',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_REJECTED => 'Ditolak',
    ];

    public const MANAGER_TAB_STATUS_MAP = [
        self::MANAGER_TAB_WAITING => [self::STATUS_SUBMITTED, self::STATUS_WAITING_RECEIVER],
        self::MANAGER_TAB_IN_PROGRESS => [
            self::STATUS_RECEIVER_RESPONDED,
            self::STATUS_HRD_PROCESSING,
            self::STATUS_WAITING_ASSESSMENT,
            self::STATUS_ASSESSMENT_IN_PROGRESS,
            self::STATUS_ASSESSMENT_COMPLETED,
            self::STATUS_COUNSELING_SCHEDULED,
            self::STATUS_COUNSELING_COMPLETED,
            self::STATUS_FINAL_EVALUATION,
            self::STATUS_FINANCE_REVIEW,
            self::STATUS_FINANCE_RETURNED,
            self::STATUS_WAITING_APPROVAL_IBU,
            self::STATUS_WAITING_APPROVAL_BAPAK,
        ],
        self::MANAGER_TAB_COMPLETED => [self::STATUS_COMPLETED],
        self::MANAGER_TAB_REJECTED => [self::STATUS_REJECTED],
    ];

    public static function isManagerGrade(?string $grade): bool
    {
        $normalized = strtoupper(trim(str_replace('_', ' ', (string) $grade)));

        return in_array($normalized, ['MANAGER', 'SENIOR MANAGER'], true);
    }

    public const ACTION_LABELS = [
        'process' => 'HRD Memproses',
        'reject' => 'Ditolak',
        'counseling_schedule' => 'Jadwal Konseling',
        'counseling_complete' => 'Konseling Selesai',
        'final_eval_approve' => 'Evaluasi Final Disetujui',
        'final_eval_reject' => 'Evaluasi Final Ditolak',
        'finance_approve' => 'Finance Menyetujui',
        'finance_reject' => 'Finance Menolak',
        'finance_return' => 'Finance Mengembalikan ke HRD',
        'hrd_appeal_approve' => 'HRD Naik Banding — Kirim Ulang ke Finance',
        'hrd_appeal_reject' => 'HRD Tolak Banding — Proses Selesai',
        'ibu_approve' => 'Waiting Approval Disetujui',
        'ibu_reject' => 'Waiting Approval Ditolak',
        'bapak_approve' => 'Waiting Approval Final Disetujui',
        'bapak_reject' => 'Waiting Approval Final Ditolak',
        'create' => 'Permohonan Diajukan',
        'store' => 'Permohonan Diajukan',
        'mutasi_notify_receiver' => 'Notifikasi Mutasi ke Manager Penerima',
        'receiver_accept' => 'Manager Penerima Menerima Mutasi',
        'receiver_reject' => 'Manager Penerima Menolak Mutasi',
        'generate_assessment' => 'Assessment Dibuat',
        'assessment_completed' => 'Assessment Selesai',
        'skip_assessment' => 'Assessment Dilewati',
        'counseling_schedule' => 'Konseling Dijadwalkan',
    ];

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    public static function actionLabel(?string $action): string
    {
        return self::ACTION_LABELS[$action] ?? ucfirst(str_replace('_', ' ', (string) $action));
    }

    public static function normalizeActorName(?string $name): string
    {
        $normalized = trim((string) $name);
        $legacyMap = [
            'Ibu Boss' => SalaryAdjustmentEmailService::LABEL_APPROVAL,
            'Bapak Boss' => SalaryAdjustmentEmailService::LABEL_APPROVAL_FINAL,
        ];

        if ($normalized === '') {
            return 'System';
        }

        return $legacyMap[$normalized] ?? $normalized;
    }

    public static function formatLogs($logs): array
    {
        return collect($logs)->map(function ($log) {
            return [
                'id' => $log->id,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'from_status_label' => self::statusLabel($log->from_status),
                'to_status_label' => self::statusLabel($log->to_status),
                'action' => $log->action,
                'action_label' => self::actionLabel($log->action),
                'actor_name' => self::normalizeActorName($log->actor_name),
                'notes' => $log->notes,
                'created_at' => $log->created_at,
            ];
        })->values()->all();
    }

    public static function statusesForManagerTab(string $tab): array
    {
        return self::MANAGER_TAB_STATUS_MAP[$tab] ?? [];
    }

    public static function statusesForHrdTab(string $tab): array
    {
        return self::HRD_TAB_STATUS_MAP[$tab] ?? [];
    }

    public static function interpretScore(float $averageScore): string
    {
        if ($averageScore >= 4.5) {
            return 'Sangat Baik';
        }
        if ($averageScore >= 3.5) {
            return 'Baik';
        }
        if ($averageScore >= 2.5) {
            return 'Cukup';
        }

        return 'Kurang';
    }

    public static function calculateRowFinalScore(float $weightPct, float $score): float
    {
        return round($weightPct * $score * 0.2, 2);
    }

    public static function hrdProcessableStatuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_RECEIVER_RESPONDED,
        ];
    }

    public static function canTransition(string $from, string $to): bool
    {
        $allowed = [
            self::STATUS_WAITING_RECEIVER => [
                self::STATUS_RECEIVER_RESPONDED,
                self::STATUS_REJECTED,
            ],
            self::STATUS_SUBMITTED => [
                self::STATUS_HRD_PROCESSING,
                self::STATUS_FINAL_EVALUATION,
                self::STATUS_REJECTED,
            ],
            self::STATUS_RECEIVER_RESPONDED => [
                self::STATUS_HRD_PROCESSING,
                self::STATUS_FINAL_EVALUATION,
                self::STATUS_REJECTED,
            ],
            self::STATUS_HRD_PROCESSING => [self::STATUS_WAITING_ASSESSMENT, self::STATUS_REJECTED],
            self::STATUS_ASSESSMENT_COMPLETED => [
                self::STATUS_COUNSELING_SCHEDULED,
                self::STATUS_FINAL_EVALUATION,
            ],
            self::STATUS_COUNSELING_SCHEDULED => [self::STATUS_COUNSELING_COMPLETED, self::STATUS_FINAL_EVALUATION],
            self::STATUS_COUNSELING_COMPLETED => [self::STATUS_FINAL_EVALUATION],
            self::STATUS_FINAL_EVALUATION => [
                self::STATUS_FINANCE_REVIEW,
                self::STATUS_WAITING_APPROVAL_IBU,
                self::STATUS_REJECTED,
            ],
            self::STATUS_FINANCE_REVIEW => [
                self::STATUS_WAITING_APPROVAL_IBU,
                self::STATUS_FINANCE_RETURNED,
            ],
            self::STATUS_FINANCE_RETURNED => [
                self::STATUS_FINANCE_REVIEW,
                self::STATUS_REJECTED,
            ],
            self::STATUS_WAITING_APPROVAL_IBU => [self::STATUS_WAITING_APPROVAL_BAPAK, self::STATUS_REJECTED],
            self::STATUS_WAITING_APPROVAL_BAPAK => [self::STATUS_COMPLETED, self::STATUS_REJECTED],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }
}
