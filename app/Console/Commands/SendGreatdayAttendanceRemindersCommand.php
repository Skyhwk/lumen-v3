<?php

namespace App\Console\Commands;

use App\Services\Greatday\AttendanceReminderDetectionService;
use App\Services\Greatday\AttendanceReminderNotifier;
use App\Support\Greatday\AttendanceReminderDevLimit;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Deteksi + kirim notifikasi reminder absensi Greatday (FCM + gd_notification + MQTT).
 */
class SendGreatdayAttendanceRemindersCommand extends Command
{
    protected $signature = 'greatday:attendance-reminder-send
                            {--date= : Tanggal YYYY-MM-DD (default: hari ini WIB)}
                            {--slot= : morning (09:00) atau evening (21:00); default otomatis berdasarkan waktu WIB}
                            {--user-id=* : Batasi ke karyawan_id}
                            {--user-ids= : CSV karyawan_id}
                            {--dry-run : Deteksi saja, tampilkan payload tanpa kirim}
                            {--redirect-to= : Uji non-production: hanya deteksi ID ini dan kirim ulang tanpa dedupe}';

    protected $description = 'Kirim reminder absen masuk/pulang ke app Attendance (shift-aware)';

    public function handle(
        AttendanceReminderDetectionService $detector,
        AttendanceReminderNotifier $notifier
    ): int {
        $date = $this->resolveDate();
        if ($date === null) {
            $this->error('Format --date tidak valid. Gunakan YYYY-MM-DD.');

            return 1;
        }

        $slot = $this->resolveSlot((string) $this->option('slot'));
        if ($slot === null) {
            $this->error('--slot harus morning atau evening.');

            return 1;
        }

        $karyawanIds = AttendanceReminderDevLimit::resolveScanIds($this->resolveKaryawanIds());
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Tanggal: ' . $date->toDateString() . ' · slot: ' . $slot . ($dryRun ? ' · DRY-RUN' : ''));
        $redirectTo = AttendanceReminderDevLimit::resolveRedirectTo(
            $this->option('redirect-to') !== null ? (int) $this->option('redirect-to') : null
        );
        if ($this->option('redirect-to') !== null && $redirectTo === null) {
            $this->error('--redirect-to hanya tersedia di non-production dan harus berupa ID karyawan positif.');

            return 1;
        }
        if ($redirectTo !== null) {
            $karyawanIds = [$redirectTo];
            $this->warn("Uji: hanya deteksi karyawan_id {$redirectTo}; pengecekan duplikat dilewati.");
        }

        $result = $detector->detect($date, $slot, $karyawanIds);
        $result['reminders'] = AttendanceReminderDevLimit::filterReminders($result['reminders']);
        $listCount = count($result['reminders']);
        $this->line(sprintf(
            'Deteksi: %d perlu reminder (scan %d, skip %d)',
            $listCount,
            $result['scanned'],
            $result['skipped']
        ));

        if ($result['reminders'] === []) {
            $this->comment('Tidak ada yang perlu dikirim.');

            return 0;
        }

        foreach ($result['reminders'] as $row) {
            $this->line(sprintf(
                '  → [%d] %s · %s',
                $row['karyawan_id'],
                $row['reminder_type'],
                $row['nama']
            ));
        }

        $send = $notifier->sendFromDetection($result, $dryRun, $redirectTo);

        if ($dryRun) {
            $this->warn("Dry-run: {$send['sent']} notifikasi siap kirim (dedupe skip: {$send['skippedDedupe']}).");
        } else {
            $this->info("Terkirim: {$send['sent']}, dedupe: {$send['skippedDedupe']}, gagal: {$send['failed']}");
        }

        if ($redirectTo !== null && !$dryRun && $listCount > 0 && $send['sent'] !== $listCount) {
            $this->warn("Perhatian: list deteksi {$listCount} baris, terkirim {$send['sent']} — cek gagal/dedupe.");
        } elseif ($redirectTo !== null && $listCount > 0 && $send['sent'] === $listCount) {
            $this->info("DEV OK: jumlah terkirim ke ID {$redirectTo} = jumlah list ({$listCount}).");
        }

        foreach ($send['errors'] as $err) {
            $this->error($err);
        }

        return $send['failed'] > 0 ? 1 : 0;
    }

    private function resolveSlot(string $raw): ?string
    {
        $slot = strtolower(trim($raw));
        if ($slot === '') {
            return Carbon::now('Asia/Jakarta')->hour < 12
                ? AttendanceReminderDetectionService::SLOT_MORNING
                : AttendanceReminderDetectionService::SLOT_EVENING;
        }

        return in_array($slot, [AttendanceReminderDetectionService::SLOT_MORNING,
            AttendanceReminderDetectionService::SLOT_EVENING], true) ? $slot : null;
    }

    private function resolveDate(): ?Carbon
    {
        $raw = trim((string) $this->option('date'));
        if ($raw === '') {
            return Carbon::now('Asia/Jakarta')->startOfDay();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $raw, 'Asia/Jakarta')->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return list<int>
     */
    private function resolveKaryawanIds(): array
    {
        $ids = [];
        foreach ((array) $this->option('user-id') as $raw) {
            $ids = array_merge($ids, $this->parseIdList((string) $raw));
        }
        $csv = trim((string) $this->option('user-ids'));
        if ($csv !== '') {
            $ids = array_merge($ids, $this->parseIdList($csv));
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }

    /**
     * @return list<int>
     */
    private function parseIdList(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $piece) {
            $id = (int) $piece;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }
}
