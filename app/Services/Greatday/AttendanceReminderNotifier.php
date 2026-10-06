<?php

namespace App\Services\Greatday;

use App\Support\Greatday\GreatdayAppData;
use App\Support\Greatday\NotificationCopy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceReminderNotifier
{
    /**
     * @param  array{date: string, slot: string, reminders: list<array<string, mixed>>}  $detectResult
     * @return array{sent: int, skipped_dedupe: int, failed: int, errors: list<string>}
     */
    public function sendFromDetection(array $detectResult, bool $dryRun = false, ?int $redirectUserId = null): array
    {
        $date = (string) ($detectResult['date'] ?? '');
        $slot = (string) ($detectResult['slot'] ?? '');
        $reminders = $detectResult['reminders'] ?? [];

        $sent = 0;
        $skippedDedupe = 0;
        $failed = 0;
        $errors = [];

        if ($reminders === []) {
            return compact('sent', 'skippedDedupe', 'failed', 'errors');
        }

        $firebase = null;
        if (!$dryRun) {
            try {
                $firebase = new FirebaseService();
            } catch (\Throwable $e) {
                return [
                    'sent' => 0,
                    'skipped_dedupe' => 0,
                    'failed' => count($reminders),
                    'errors' => ['Firebase: ' . $e->getMessage()],
                ];
            }
        }

        $devRedirect = $redirectUserId !== null && $redirectUserId > 0;

        foreach ($reminders as $row) {
            $originalUserId = (int) ($row['karyawan_id'] ?? 0);
            if ($originalUserId <= 0) {
                continue;
            }

            $recipientId = $devRedirect ? (int) $redirectUserId : $originalUserId;

            $reminderType = (string) ($row['reminder_type'] ?? '');
            $payload = $this->buildPayload($date, $reminderType, $row);
            if ($payload === null) {
                continue;
            }

            $payload['reminder_date'] = $date;
            $payload['reminder_slot'] = $slot;

            if ($devRedirect) {
                $payload['dev_redirect'] = true;
                $payload['dev_original_karyawan_id'] = $originalUserId;
                $payload['dev_original_nama'] = (string) ($row['nama'] ?? '');
                $payload['body'] .= sprintf(
                    ' [DEV: semula untuk %s (ID %d).]',
                    $payload['dev_original_nama'] !== '' ? $payload['dev_original_nama'] : 'karyawan',
                    $originalUserId
                );
            }

            if (!$devRedirect && $this->alreadySentToday($recipientId, $date, $reminderType)) {
                $skippedDedupe++;

                continue;
            }

            if ($dryRun) {
                $sent++;

                continue;
            }

            try {
                $result = $firebase->sendNotifications([$recipientId], $payload);
                if (!empty($result['success'])) {
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = "original {$originalUserId} → {$recipientId}: " . ($result['message'] ?? 'gagal kirim');
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "original {$originalUserId} → {$recipientId}: " . $e->getMessage();
            }
        }

        Log::info('greatday.attendance_reminder_send', [
            'date' => $date,
            'slot' => $slot,
            'dry_run' => $dryRun,
            'dev_redirect_to' => $devRedirect ? $redirectUserId : null,
            'list_count' => count($reminders),
            'sent' => $sent,
            'skipped_dedupe' => $skippedDedupe,
            'failed' => $failed,
        ]);

        return compact('sent', 'skippedDedupe', 'failed', 'errors');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function buildPayload(string $date, string $reminderType, array $row): ?array
    {
        if ($reminderType === 'missing_masuk') {
            return NotificationCopy::attendanceReminderMissingMasuk($date, $row['time_in'] ?? null);
        }
        if ($reminderType === 'missing_pulang') {
            return NotificationCopy::attendanceReminderMissingPulang($date, $row['time_out'] ?? null);
        }

        return null;
    }

    private function alreadySentToday(int $userId, string $dateYmd, string $reminderType): bool
    {
        $start = Carbon::parse($dateYmd, 'Asia/Jakarta')->startOfDay();
        $end = $start->copy()->endOfDay();

        $title = $reminderType === 'missing_pulang'
            ? 'Kehadiran · absen pulang belum tercatat'
            : 'Kehadiran · absen masuk belum tercatat';

        return GreatdayAppData::notificationQuery()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->where('title', $title)
            ->exists();
    }
}
