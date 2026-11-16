<?php

namespace App\Console\Commands;

use App\Models\Hr\HrRequest;
use App\Models\IntilabInternal\AttendanceCorrections;
use App\Services\Hr\Greatday\AttendanceCorrectionAbsensiApplyService;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\WorkflowStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReapplyApprovedAttendanceCorrectionsCommand extends Command
{
    protected $signature = 'hr:reapply-attendance-corrections
                            {--id= : ID koreksi (hr_request.id atau legacy attendance_corrections.id)}
                            {--user= : karyawan_id pemohon}
                            {--from= : Tanggal koreksi (correction_date) awal YYYY-MM-DD}
                            {--to= : Tanggal koreksi akhir YYYY-MM-DD}
                            {--dry-run : Preview tanpa menulis ke tabel absensi}';

    protected $description = 'Terapkan ulang koreksi absensi Approved HRD ke tabel absensi (shift-aware)';

    public function handle(AttendanceCorrectionAbsensiApplyService $applyService): int
    {
        [$from, $to] = $this->resolveOptionalRange();
        if ($from === false) {
            return 1;
        }

        $id = $this->option('id');
        $id = $id !== null && $id !== '' ? (int) $id : null;
        $userId = $this->option('user');
        $userId = $userId !== null && $userId !== '' ? (int) $userId : null;
        $dryRun = (bool) $this->option('dry-run');

        $items = $this->collectCorrections($id, $userId, $from, $to);
        if ($items->isEmpty()) {
            $this->warn('Tidak ada koreksi absensi Approved HRD yang cocok dengan filter.');

            return 0;
        }

        $this->info('Ditemukan ' . $items->count() . ' koreksi Approved HRD.' . ($dryRun ? ' (dry-run)' : ''));
        $this->line('');

        $applied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($items as $item) {
            $label = sprintf(
                '[%s] karyawan=%d tanggal=%s type=%s jam=%s (sumber=%s id=%d)',
                $item['label'],
                $item['karyawan_id'],
                $item['correction_date'],
                $item['correction_type'],
                $item['correction_time'],
                $item['source'],
                $item['source_id']
            );

            $absensiDate = AttendanceCorrectionAbsensiApplyService::resolveStorageDate(
                $item['correction_date'],
                $this->normalizeTimeForPreview($item['correction_time']),
                $this->isCheckInType($item['correction_type']),
                $item['shift_on_date'],
                $item['shift_prev_date']
            );
            $status = $this->isCheckInType($item['correction_type']) ? 'Masuk' : 'Keluar';
            $detail = " → absensi tanggal={$absensiDate} status={$status}";

            if ($dryRun) {
                $wouldSkip = $applyService->isAlreadyApplied(
                    $item['karyawan_id'],
                    $item['correction_date'],
                    $item['correction_type'],
                    $item['correction_time']
                );
                if ($wouldSkip) {
                    $this->line("  [dry-run skip] {$label}{$detail} (sudah ada di absensi)");
                    $skipped++;
                } else {
                    $this->line("  [dry-run apply] {$label}{$detail}");
                    $applied++;
                }
                continue;
            }

            try {
                $changed = $applyService->apply(
                    $item['karyawan_id'],
                    $item['correction_date'],
                    $item['correction_type'],
                    $item['correction_time']
                );
                if ($changed) {
                    $this->info("  [ok] {$label}{$detail}");
                    $applied++;
                } else {
                    $this->line("  [skip] {$label}{$detail} (sudah ada di absensi)");
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  [gagal] {$label} — {$e->getMessage()}");
            }
        }

        $this->line('');
        $summary = ($dryRun ? 'Dry-run' : 'Selesai') . ": {$applied} akan/sudah diterapkan, {$skipped} dilewati";
        if ($failed > 0) {
            $summary .= ", {$failed} gagal";
        }
        $this->info($summary . '.');

        return $failed > 0 ? 1 : 0;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectCorrections(?int $id, ?int $userId, ?string $from, ?string $to): Collection
    {
        if ($id !== null) {
            $single = $this->findSingleById($id);
            if ($single === null) {
                return collect();
            }
            if ($userId !== null && (int) $single['karyawan_id'] !== $userId) {
                return collect();
            }
            if (!$this->dateInRange($single['correction_date'], $from, $to)) {
                return collect();
            }

            return collect([$single]);
        }

        $items = $this->loadFromHrRequests($userId, $from, $to);
        $legacyOnly = $this->loadLegacyNotMapped($userId, $from, $to);

        return $items
            ->merge($legacyOnly)
            ->unique(fn ($row) => $this->dedupeKey($row))
            ->sortBy([
                ['correction_date', 'asc'],
                ['karyawan_id', 'asc'],
                ['correction_time', 'asc'],
            ])
            ->values();
    }

    /** @return array<string, mixed>|null */
    private function findSingleById(int $id): ?array
    {
        $hr = HrRequestResolver::findByPortalSliceId(HrRequest::TYPE_ATTENDANCE_CORRECTION, $id);
        if ($hr) {
            $hr->loadMissing('attendanceCorrectionDetail');
            if ($hr->status === WorkflowStatus::APPROVED_HRD && $hr->attendanceCorrectionDetail) {
                return $this->mapHrRow($hr);
            }
        }

        $legacy = AttendanceCorrections::query()->find($id);
        if ($legacy && (string) $legacy->status === WorkflowStatus::APPROVED_HRD) {
            return $this->mapLegacyRow($legacy);
        }

        $hrDirect = HrRequest::with('attendanceCorrectionDetail')
            ->where('request_type', HrRequest::TYPE_ATTENDANCE_CORRECTION)
            ->where('id', $id)
            ->where('is_active', true)
            ->first();
        if ($hrDirect && $hrDirect->status === WorkflowStatus::APPROVED_HRD && $hrDirect->attendanceCorrectionDetail) {
            return $this->mapHrRow($hrDirect);
        }

        return null;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function loadFromHrRequests(?int $userId, ?string $from, ?string $to): Collection
    {
        $query = HrRequest::with('attendanceCorrectionDetail')
            ->where('request_type', HrRequest::TYPE_ATTENDANCE_CORRECTION)
            ->where('status', WorkflowStatus::APPROVED_HRD)
            ->where('is_active', true);

        if ($userId !== null) {
            $query->where('karyawan_id', $userId);
        }

        $query->whereHas('attendanceCorrectionDetail', function ($detail) use ($from, $to) {
            if ($from !== null) {
                $detail->where('correction_date', '>=', $from);
            }
            if ($to !== null) {
                $detail->where('correction_date', '<=', $to);
            }
        });

        return $query->orderBy('id')->get()->map(function (HrRequest $row) {
            return $this->mapHrRow($row);
        })->filter()->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function loadLegacyNotMapped(?int $userId, ?string $from, ?string $to): Collection
    {
        $query = AttendanceCorrections::query()
            ->where('status', WorkflowStatus::APPROVED_HRD);

        if ($userId !== null) {
            $query->where('employee_id', $userId);
        }
        if ($from !== null) {
            $query->where('date', '>=', $from);
        }
        if ($to !== null) {
            $query->where('date', '<=', $to);
        }

        return $query->orderBy('id')->get()->filter(function (AttendanceCorrections $row) {
            return HrRequestResolver::findMappedFromLegacyRow(
                HrRequest::TYPE_ATTENDANCE_CORRECTION,
                (int) $row->id
            ) === null;
        })->map(function (AttendanceCorrections $row) {
            return $this->mapLegacyRow($row);
        })->filter()->values();
    }

    /** @return array<string, mixed>|null */
    private function mapHrRow(HrRequest $row): ?array
    {
        $detail = $row->attendanceCorrectionDetail;
        if (!$detail) {
            return null;
        }

        $date = (string) $detail->correction_date;
        $shifts = $this->shiftContext((int) $row->karyawan_id, $date);

        return [
            'label' => 'hr_request',
            'source' => 'hr_request',
            'source_id' => (int) $row->id,
            'karyawan_id' => (int) $row->karyawan_id,
            'correction_date' => $date,
            'correction_type' => (string) $detail->correction_type,
            'correction_time' => (string) $detail->correction_time,
            'shift_on_date' => $shifts['on'],
            'shift_prev_date' => $shifts['prev'],
        ];
    }

    /** @return array<string, mixed> */
    private function mapLegacyRow(AttendanceCorrections $row): array
    {
        $date = (string) $row->date;
        $shifts = $this->shiftContext((int) $row->employee_id, $date);

        return [
            'label' => 'legacy',
            'source' => 'legacy',
            'source_id' => (int) $row->id,
            'karyawan_id' => (int) $row->employee_id,
            'correction_date' => $date,
            'correction_type' => (string) $row->type,
            'correction_time' => (string) $row->time,
            'shift_on_date' => $shifts['on'],
            'shift_prev_date' => $shifts['prev'],
        ];
    }

    /** @return array{on:?string, prev:?string} */
    private function shiftContext(int $karyawanId, string $correctionDate): array
    {
        $conn = (string) config('greatday.produksi_connection', config('database.default', 'mysql'));
        $prev = Carbon::parse($correctionDate)->subDay()->toDateString();

        $on = \App\Models\ShiftKaryawan::on($conn)
            ->where('karyawan_id', $karyawanId)
            ->where('tanggal', $correctionDate)
            ->value('shift');
        $prevShift = \App\Models\ShiftKaryawan::on($conn)
            ->where('karyawan_id', $karyawanId)
            ->where('tanggal', $prev)
            ->value('shift');

        return ['on' => $on ? (string) $on : null, 'prev' => $prevShift ? (string) $prevShift : null];
    }

    /** @return array{0:?string,1:?string}|false */
    private function resolveOptionalRange()
    {
        $from = trim((string) $this->option('from'));
        $to = trim((string) $this->option('to'));

        if ($from === '' && $to === '') {
            return [null, null];
        }

        if ($from === '' || $to === '') {
            $this->error('Pakai --from dan --to bersama-sama (atau keduanya kosong untuk semua tanggal).');

            return false;
        }

        if (!$this->isYmd($from) || !$this->isYmd($to)) {
            $this->error('Format --from/--to harus YYYY-MM-DD.');

            return false;
        }

        return [$from, $to];
    }

    private function dateInRange(string $date, ?string $from, ?string $to): bool
    {
        if ($from !== null && $date < $from) {
            return false;
        }
        if ($to !== null && $date > $to) {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $row */
    private function dedupeKey(array $row): string
    {
        return implode('|', [
            $row['karyawan_id'],
            $row['correction_date'],
            strtolower(preg_replace('/[\s_-]+/', '', (string) $row['correction_type'])),
            $this->normalizeTimeForPreview((string) $row['correction_time']),
        ]);
    }

    private function isCheckInType(string $type): bool
    {
        $normalized = strtolower(preg_replace('/[\s_-]+/', '', trim($type)));

        return $normalized === 'checkin';
    }

    private function normalizeTimeForPreview(string $time): string
    {
        $time = trim($time);
        if ($time !== '' && preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return $time;
    }

    private function isYmd(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}
