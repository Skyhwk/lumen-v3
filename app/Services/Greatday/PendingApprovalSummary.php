<?php

namespace App\Services\Greatday;

use Carbon\Carbon;

/**
 * Meta ringkas untuk kartu antrian persetujuan atasan (mobile Greatday).
 */
class PendingApprovalSummary
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forController(string $controller, object $raw): array
    {
        switch ($controller) {
            case 'OvertimeRequestsController':
                return self::overtime($raw);
            case 'LeaveRequestsController':
                return self::leave($raw);
            case 'PermissionRequestsController':
                return self::permission($raw);
            case 'AttendanceCorrectionsController':
                return self::attendanceCorrection($raw);
            case 'OvertimeReimbursementsController':
                return self::overtimeReimbursement($raw);
            case 'ConsultationRequestsController':
                return self::consultation($raw);
            default:
                return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function overtime(object $raw): array
    {
        $highlights = [];

        $memberCount = self::countOvertimeMembers($raw);
        if ($memberCount > 0) {
            $highlights[] = ['kind' => 'people', 'count' => $memberCount];
        }

        $period = self::dateRangeChip($raw->start_date ?? null, $raw->end_date ?? null);
        if ($period) {
            $highlights[] = $period;
        }

        $time = self::timeRangeChip($raw->start_time ?? null, $raw->end_time ?? null);
        if ($time) {
            $highlights[] = $time;
        }

        if (!empty($raw->department_name)) {
            $highlights[] = ['kind' => 'text', 'value' => (string) $raw->department_name];
        }

        return $highlights;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function leave(object $raw): array
    {
        $highlights = [];

        if (!empty($raw->type)) {
            $highlights[] = ['kind' => 'text', 'value' => (string) $raw->type];
        }

        $days = self::inclusiveDays($raw->start_date ?? null, $raw->end_date ?? null);
        if ($days !== null) {
            $highlights[] = ['kind' => 'days', 'count' => $days];
        }

        $period = self::dateRangeChip($raw->start_date ?? null, $raw->end_date ?? null);
        if ($period) {
            $highlights[] = $period;
        }

        return $highlights;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function permission(object $raw): array
    {
        $highlights = [];

        if (!empty($raw->type)) {
            $highlights[] = ['kind' => 'text', 'value' => (string) $raw->type];
        }

        $days = self::inclusiveDays($raw->start_date ?? null, $raw->end_date ?? null);
        if ($days !== null && $days > 1) {
            $highlights[] = ['kind' => 'days', 'count' => $days];
        }

        $period = self::dateRangeChip($raw->start_date ?? null, $raw->end_date ?? null);
        if ($period) {
            $highlights[] = $period;
        }

        $time = self::timeRangeChip($raw->start_time ?? null, $raw->end_time ?? null);
        if ($time) {
            $highlights[] = $time;
        }

        return $highlights;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function attendanceCorrection(object $raw): array
    {
        $highlights = [];

        if (!empty($raw->type)) {
            $highlights[] = ['kind' => 'text', 'value' => (string) $raw->type];
        }

        if (!empty($raw->date)) {
            $highlights[] = [
                'kind' => 'date',
                'value' => self::formatDate($raw->date),
            ];
        }

        if (!empty($raw->time)) {
            $highlights[] = [
                'kind' => 'time',
                'value' => self::formatTime($raw->time),
            ];
        }

        return $highlights;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function overtimeReimbursement(object $raw): array
    {
        $highlights = [];

        if (isset($raw->amount) && $raw->amount !== '' && $raw->amount !== null) {
            $highlights[] = [
                'kind' => 'amount',
                'value' => (string) $raw->amount,
            ];
        }

        $period = self::dateRangeChip($raw->start_date ?? null, $raw->end_date ?? null);
        if ($period) {
            $highlights[] = $period;
        }

        if (!empty($raw->no_document)) {
            $highlights[] = ['kind' => 'document', 'value' => (string) $raw->no_document];
        }

        return $highlights;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function consultation(object $raw): array
    {
        $highlights = [];

        if (!empty($raw->topic ?? $raw->type ?? null)) {
            $highlights[] = ['kind' => 'text', 'value' => (string) ($raw->topic ?? $raw->type)];
        }

        if (!empty($raw->date ?? $raw->consultation_date ?? null)) {
            $highlights[] = [
                'kind' => 'date',
                'value' => self::formatDate($raw->date ?? $raw->consultation_date),
            ];
        }

        return $highlights;
    }

    private static function countOvertimeMembers(object $raw): int
    {
        $members = $raw->members ?? null;
        if (!is_iterable($members)) {
            return 0;
        }

        $count = 0;
        foreach ($members as $member) {
            $active = is_object($member) ? ($member->is_active ?? true) : ($member['is_active'] ?? true);
            if ($active === false || $active === 0 || $active === '0') {
                continue;
            }
            $count++;
        }

        return $count;
    }

    private static function inclusiveDays($start, $end): ?int
    {
        if (!$start || !$end) {
            return null;
        }

        try {
            $s = Carbon::parse($start)->startOfDay();
            $e = Carbon::parse($end)->startOfDay();

            return $s->diffInDays($e) + 1;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function dateRangeChip($start, $end): ?array
    {
        if (!$start && !$end) {
            return null;
        }

        if ($start && $end) {
            $s = Carbon::parse($start);
            $e = Carbon::parse($end);
            if ($s->isSameDay($e)) {
                return ['kind' => 'date', 'value' => self::formatDate($start)];
            }

            return [
                'kind' => 'date_range',
                'start' => self::formatDate($start),
                'end' => self::formatDate($end),
            ];
        }

        return ['kind' => 'date', 'value' => self::formatDate($start ?: $end)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function timeRangeChip($start, $end): ?array
    {
        if (!$start && !$end) {
            return null;
        }

        if ($start && $end) {
            return [
                'kind' => 'time_range',
                'start' => self::formatTime($start),
                'end' => self::formatTime($end),
            ];
        }

        return ['kind' => 'time', 'value' => self::formatTime($start ?: $end)];
    }

    private static function formatDate($value): string
    {
        try {
            return Carbon::parse($value)->locale('id')->translatedFormat('d M Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private static function formatTime($value): string
    {
        $str = (string) $value;
        if (preg_match('/^\d{2}:\d{2}/', $str)) {
            return substr($str, 0, 5);
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable $e) {
            return $str;
        }
    }
}
