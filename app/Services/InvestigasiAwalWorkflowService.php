<?php

namespace App\Services;

use App\Support\InvestigasiAwalStatus;

final class InvestigasiAwalWorkflowService
{
    /** @var array<string, string> */
    private const STATUS_LABELS = [
        InvestigasiAwalStatus::DRAFT => 'Draft',
        InvestigasiAwalStatus::MENUNGGU_HASIL => 'Menunggu Hasil (G.1)',
        InvestigasiAwalStatus::MENUNGGU_PENGESAHAN => 'Menunggu Pengesahan',
        InvestigasiAwalStatus::ISSUED => 'Terbit',
        InvestigasiAwalStatus::CANCELLED => 'Dibatalkan',
    ];

    /** @var array<string, string> */
    private const ACTION_LABELS = [
        'create' => 'Investigasi Dibuat',
        'submit' => 'Investigasi Diajukan',
        'save_g1' => 'Hasil G.1 Disimpan',
        'approve_legal' => 'Investigasi Diterbitkan (F1)',
        'approve_diketahui' => 'Pengesahan Diketahui',
        'reject_pengesahan' => 'Dikembalikan ke Pembuat',
    ];

    public static function statusLabel(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $normalized = trim((string) $status);
        if ($normalized === '') {
            return null;
        }

        return self::STATUS_LABELS[$normalized]
            ?? ucfirst(str_replace('_', ' ', $normalized));
    }

    public static function transitionLabel(?string $fromStatus, ?string $toStatus, ?string $action = null): ?string
    {
        $from = self::statusLabel($fromStatus);
        $to = self::statusLabel($toStatus);

        if ($action === 'create' && $to !== null) {
            return 'Status: ' . $to;
        }

        if ($action === 'approve_legal') {
            return $from !== null
                ? $from . ' → Investigasi diterbitkan'
                : 'Investigasi diterbitkan';
        }

        if ($from !== null && $to !== null && $from !== $to) {
            return $from . ' → ' . $to;
        }

        if ($to !== null) {
            return 'Status: ' . $to;
        }

        return null;
    }

    public static function actionLabel(?string $action): string
    {
        return self::ACTION_LABELS[$action] ?? ucfirst(str_replace('_', ' ', (string) $action));
    }

    /** Catatan timeline disipliner cukup judul aksi; detail panjang tidak ditampilkan. */
    public static function notesForTimeline(string $action, ?string $notes): ?string
    {
        if ($action === 'reject_pengesahan') {
            $reason = trim((string) $notes);

            return $reason !== '' ? $reason : null;
        }

        return null;
    }

    public static function formatLogs($logs): array
    {
        return collect($logs)->map(function ($log) {
            $fromLabel = self::statusLabel($log->from_status);
            $toLabel = self::statusLabel($log->to_status);
            $action = (string) ($log->action ?? '');

            return [
                'id' => $log->id,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'from_status_label' => $fromLabel,
                'to_status_label' => $toLabel,
                'transition_label' => self::transitionLabel($log->from_status, $log->to_status, $action),
                'action' => $log->action,
                'action_label' => self::actionLabel($log->action),
                'actor_name' => trim((string) ($log->actor_name ?? '')) !== '' ? $log->actor_name : 'System',
                'notes' => self::notesForTimeline($action, $log->notes),
                'created_at' => $log->created_at,
                'timeline_source' => 'investigasi',
            ];
        })->values()->all();
    }
}