<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardAbsensiKaryawanService
{
    public function build(int $managerId, string $period = 'weekly', int $offset = 0, ?string $month = null): array
    {
        Carbon::setLocale('id');
        $period = $period === 'monthly' ? 'monthly' : 'weekly';
        $range = $this->resolvePeriod($period, $offset, $month);
        $manager = MasterKaryawan::where('id', $managerId)->first();
        $team = $this->subordinates($managerId);

        if ($team->isEmpty()) {
            return $this->emptyPayload($manager, $range, $period);
        }

        $ids = $team->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $start = $range['start']->toDateString();
        $end = $range['end']->toDateString();
        $endPlus = $range['end']->copy()->addDay()->toDateString();

        $shifts = DB::table('shift_karyawan')
            ->whereIn('karyawan_id', $ids)
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->groupBy(fn ($row) => $row->karyawan_id . '|' . Carbon::parse($row->tanggal)->toDateString());

        $punches = DB::table('absensi')
            ->whereIn('karyawan_id', $ids)
            ->whereBetween('tanggal', [$start, $endPlus])
            ->get(['karyawan_id', 'tanggal', 'jam', 'status'])
            ->groupBy(fn ($row) => $row->karyawan_id . '|' . Carbon::parse($row->tanggal)->toDateString());

        $employees = $team->map(function ($employee) use ($range, $shifts, $punches) {
            return $this->summarizeEmployee($employee, $range, $shifts, $punches);
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $clusters = $this->buildClusters($employees);

        return [
            'success' => true,
            'data' => [
                'manager' => $this->presentEmployee($manager),
                'period' => [
                    'type' => $period,
                    'label' => $range['label'],
                    'start' => $start,
                    'end' => $end,
                ],
                'summary' => [
                    'total' => $employees->count(),
                    'with_attendance' => $employees->where('attendance_days', '>', 0)->count(),
                    'cluster_count' => count($clusters),
                ],
                'clusters' => $clusters,
                'employees' => $employees->values()->all(),
            ],
        ];
    }

    private function subordinates(int $managerId): Collection
    {
        $team = GetBawahan::where('id', $managerId)
            ->get()
            ->filter(fn ($employee) => (int) $employee->id !== $managerId && (int) $employee->is_active === 1)
            ->unique('id')
            ->values();

        $jabatan = DB::table('master_jabatan')
            ->whereIn('id', $team->pluck('id_jabatan')->filter()->unique()->all())
            ->pluck('nama_jabatan', 'id');
        $divisi = DB::table('master_divisi')
            ->whereIn('id', $team->pluck('id_department')->filter()->unique()->all())
            ->pluck('nama_divisi', 'id');

        return $team->map(function ($employee) use ($jabatan, $divisi) {
            $employee->jabatan_nama = $jabatan[$employee->id_jabatan] ?? $employee->jabatan;
            $employee->department = $divisi[$employee->id_department] ?? $employee->department;
            return $employee;
        });
    }

    private function resolvePeriod(string $period, int $offset, ?string $month = null): array
    {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $offset = (int) $offset;

        if ($period === 'monthly') {
            $cursor = $this->resolveMonthCursor($today, $offset, $month);
            $start = $cursor->copy()->startOfMonth();
            $end = $cursor->copy()->endOfMonth()->startOfDay();
            if ($start->isSameMonth($today) && $end->gt($today)) {
                $end = $today->copy();
            }
            $label = $start->translatedFormat('F Y');
        } else {
            $cursor = $today->copy()->startOfWeek(Carbon::MONDAY)->addWeeks($offset);
            $start = $cursor->copy();
            $end = $cursor->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();
            if ($offset === 0 && $end->gt($today)) {
                $end = $today->copy();
            }
            $label = $start->translatedFormat('d M') . ' – ' . $end->translatedFormat('d M Y');
        }

        return compact('start', 'end', 'label');
    }

    private function resolveMonthCursor(Carbon $today, int $offset, ?string $month): Carbon
    {
        if ($month && preg_match('/^(\d{4})-(\d{2})$/', $month, $match)) {
            return Carbon::createFromDate((int) $match[1], (int) $match[2], 1, 'Asia/Jakarta')->startOfMonth();
        }

        return $today->copy()->startOfMonth()->addMonths($offset);
    }

    private function summarizeEmployee($employee, array $range, Collection $shifts, Collection $punches): array
    {
        $inSeconds = [];
        $outSeconds = [];
        $inDeltas = [];
        $outDeltas = [];
        $shiftNames = [];
        $shiftStats = [];
        $cursor = $range['start']->copy();

        while ($cursor->lte($range['end'])) {
            $date = $cursor->toDateString();
            $next = $cursor->copy()->addDay()->toDateString();
            $todayPunches = $punches->get($employee->id . '|' . $date, collect());
            $shift = $shifts->get($employee->id . '|' . $date);
            $shiftRow = $shift ? $shift->first() : null;
            $hasAssignedShift = (bool) $shiftRow;
            $fallback = $this->defaultShiftForEmployee($employee);

            if ($hasAssignedShift) {
                $shiftName = $shiftRow->shift;
                $timeIn = $shiftRow->time_in ?: $fallback['time_in'];
                $timeOut = $shiftRow->time_out ?: $fallback['time_out'];
            } else {
                $shiftName = $fallback['name'];
                $timeIn = $fallback['time_in'];
                $timeOut = $fallback['time_out'];
            }

            if ($hasAssignedShift && strtoupper(trim((string) $shiftName)) === 'OFF') {
                $cursor->addDay();
                continue;
            }

            // Tanpa baris shift_karyawan: Senin-Jumat mengikuti jam default divisi.
            // Sabtu-Minggu opsional, hanya dihitung jika ada absensi.
            if (!$hasAssignedShift && $cursor->isWeekend() && $todayPunches->isEmpty()) {
                $cursor->addDay();
                continue;
            }

            $normalizedName = $this->normalizeShiftName($shiftName);
            $shiftNames[] = $normalizedName;
            if (!isset($shiftStats[$normalizedName])) {
                $shiftStats[$normalizedName] = ['count' => 0, 'time_in' => $timeIn, 'time_out' => $timeOut];
            }
            $shiftStats[$normalizedName]['count']++;
            $shiftStats[$normalizedName]['time_in'] = $timeIn;
            $shiftStats[$normalizedName]['time_out'] = $timeOut;

            [$masuk, $keluar] = $this->resolveInOut(
                $todayPunches,
                $punches->get($employee->id . '|' . $next, collect()),
                $normalizedName,
                $timeIn,
                $timeOut
            );

            if ($masuk !== null) {
                $inSeconds[] = $masuk;
                $scheduleIn = $this->toSeconds($timeIn) ?? $this->toSeconds('08:00:00');
                $inDeltas[] = $masuk - $scheduleIn;
            }
            if ($keluar !== null) {
                $outSeconds[] = $keluar;
                $scheduleOut = $this->toSeconds($timeOut) ?? $this->toSeconds('17:00:00');
                if ($keluar >= 86400) {
                    $scheduleOut += 86400;
                }
                $outDeltas[] = $keluar - $scheduleOut;
            }

            $cursor->addDay();
        }

        $avgIn = $this->averageSeconds($inSeconds);
        $avgOut = $this->averageSeconds($outSeconds);
        $avgInDelta = $this->averageSeconds($inDeltas);
        $avgOutDelta = $this->averageSeconds($outDeltas);
        $rule = $this->resolveShiftRule($shiftStats);

        return array_merge($this->presentEmployee($employee), [
            'cluster' => $rule['key'],
            'cluster_label' => $rule['title'],
            'shift_label' => $rule['title'] . ' ' . $rule['schedule'],
            'schedule_in' => $rule['time_in'],
            'schedule_out' => $rule['time_out'],
            'avg_in' => $this->formatClock($avgIn),
            'avg_out' => $this->formatClock($avgOut),
            'avg_in_seconds' => $avgIn,
            'avg_out_seconds' => $avgOut,
            'avg_in_delta' => $avgInDelta,
            'avg_out_delta' => $avgOutDelta,
            'in_delta_label' => $this->formatDelta($avgInDelta, 'lebih awal dari jam masuk', 'lebih telat dari jam masuk'),
            'out_delta_label' => $this->formatDelta($avgOutDelta, 'lebih cepat dari jam pulang', 'lebih lama dari jam pulang'),
            'attendance_days' => max(count($inSeconds), count($outSeconds)),
            'in_days' => count($inSeconds),
            'out_days' => count($outSeconds),
            'shifts' => array_values(array_unique($shiftNames)),
        ]);
    }

    /**
     * Aturan jam kerja mengikuti shift_karyawan / ModalTableShiftKaryawan.
     * Kelompok berdasarkan pemakaian nyata di master_divisi:
     * - SHTEKNISI → Technical Assurance / TEKNIS
     * - SHANALYST → Analyst / Laboratorium (jam 07:00-16:00)
     * - SHADMSAMPLING → Sampling (jam 07:00-16:00)
     * - SHOB/security → General Affair
     * Tanpa baris shift dan SHREGULAR: jam kantor.
     */
    private function shiftRules(): array
    {
        return [
            'KANTOR' => ['title' => 'Jam kantor', 'time_in' => '08:00', 'time_out' => '17:00', 'family' => 'kantor', 'family_label' => 'Jam kantor', 'order' => 1, 'subtitle' => 'Masuk 08:00, pulang 17:00. Termasuk karyawan tanpa shift dan SHREGULAR.'],
            'SHANALYST' => ['title' => 'Shift analis', 'time_in' => '07:00', 'time_out' => '16:00', 'family' => 'laboratorium', 'family_label' => 'Laboratorium & Sampling', 'order' => 2, 'subtitle' => 'Divisi Analyst / Laboratorium. Masuk 07:00, pulang 16:00. Hanya dibanding sesama analis.'],
            'SHADMSAMPLING' => ['title' => 'Shift admin sampling', 'time_in' => '07:00', 'time_out' => '16:00', 'family' => 'laboratorium', 'family_label' => 'Laboratorium & Sampling', 'order' => 3, 'subtitle' => 'Divisi Sampling. Masuk 07:00, pulang 16:00. Hanya dibanding sesama admin sampling.'],
            'SHTEKNISI' => ['title' => 'Shift teknisi', 'time_in' => '10:00', 'time_out' => '19:00', 'family' => 'teknisi', 'family_label' => 'Teknisi', 'order' => 4, 'subtitle' => 'Divisi Technical Assurance. Masuk 10:00, pulang 19:00. Hanya dibanding sesama teknisi.'],
            'SHOB' => ['title' => 'Shift OB pagi', 'time_in' => '06:00', 'time_out' => '15:00', 'family' => 'operasional', 'family_label' => 'General Affair', 'order' => 5, 'subtitle' => 'Divisi General Affair. Masuk 06:00, pulang 15:00. Hanya dibanding sesama OB pagi.'],
            'SHOB2' => ['title' => 'Shift OB siang', 'time_in' => '09:00', 'time_out' => '18:00', 'family' => 'operasional', 'family_label' => 'General Affair', 'order' => 6, 'subtitle' => 'Divisi General Affair. Masuk 09:00, pulang 18:00. Hanya dibanding sesama OB siang.'],
            'SWOB' => ['title' => 'Shift OB setengah hari', 'time_in' => '07:00', 'time_out' => '12:00', 'family' => 'operasional', 'family_label' => 'General Affair', 'order' => 7, 'subtitle' => 'Divisi General Affair. Masuk 07:00, pulang 12:00. Hanya dibanding sesama shift ini.'],
            'SWOB2' => ['title' => 'Shift OB sore', 'time_in' => '09:00', 'time_out' => '18:00', 'family' => 'operasional', 'family_label' => 'General Affair', 'order' => 8, 'subtitle' => 'Divisi General Affair. Masuk 09:00, pulang 18:00. Hanya dibanding sesama OB sore.'],
            'SHSECURITY' => ['title' => 'Shift security siang', 'time_in' => '08:00', 'time_out' => '20:00', 'family' => 'security', 'family_label' => 'Security', 'order' => 9, 'subtitle' => 'Divisi General Affair. Masuk 08:00, pulang 20:00. Hanya dibanding sesama security siang.'],
            'SHSECURITY2' => ['title' => 'Shift security malam', 'time_in' => '20:00', 'time_out' => '08:00', 'family' => 'security', 'family_label' => 'Security', 'order' => 10, 'subtitle' => 'Divisi General Affair. Masuk 20:00, pulang 08:00 keesokan harinya. Hanya dibanding sesama security malam.'],
            'SHSECURITYGO1' => ['title' => 'Shift security GO 12 jam', 'time_in' => '07:00', 'time_out' => '19:00', 'family' => 'security', 'family_label' => 'Security', 'order' => 11, 'subtitle' => 'Divisi General Affair. Masuk 07:00, pulang 19:00. Hanya dibanding sesama shift ini.'],
            'SHSECURITYGO2' => ['title' => 'Shift security GO 8 jam', 'time_in' => '07:00', 'time_out' => '15:00', 'family' => 'security', 'family_label' => 'Security', 'order' => 12, 'subtitle' => 'Divisi General Affair. Masuk 07:00, pulang 15:00. Hanya dibanding sesama shift ini.'],
            '24JAM' => ['title' => 'Shift 24 jam', 'time_in' => '08:00', 'time_out' => '08:00', 'family' => 'operasional', 'family_label' => 'General Affair', 'order' => 13, 'subtitle' => 'Shift lintas hari. Hanya dibanding sesama shift 24 jam.'],
        ];
    }

    private function defaultShiftForEmployee($employee): array
    {
        $divisionId = (int) ($employee->id_department ?? 0);
        $divisionName = strtoupper(trim((string) ($employee->department ?? '')));

        // SHTEKNISI 10:00-19:00 — Technical Assurance / TEKNIS
        if (in_array($divisionId, [16, 22], true) || strpos($divisionName, 'TECHNICAL ASSURANCE') !== false || $divisionName === 'TEKNIS') {
            return ['name' => 'SHTEKNISI', 'time_in' => '10:00:00', 'time_out' => '19:00:00'];
        }

        // SHANALYST 07:00-16:00 — Analyst / Laboratorium
        if (in_array($divisionId, [20, 21], true) || $divisionName === 'ANALYST' || $divisionName === 'LABORATORIUM') {
            return ['name' => 'SHANALYST', 'time_in' => '07:00:00', 'time_out' => '16:00:00'];
        }

        // SHADMSAMPLING 07:00-16:00 — Sampling
        if ($divisionId === 14 || $divisionName === 'SAMPLING') {
            return ['name' => 'SHADMSAMPLING', 'time_in' => '07:00:00', 'time_out' => '16:00:00'];
        }

        return ['name' => 'KANTOR', 'time_in' => '08:00:00', 'time_out' => '17:00:00'];
    }

    private function normalizeShiftName($shiftName): string
    {
        $name = strtoupper(trim((string) $shiftName));
        if ($name === '' || $name === 'SHREGULAR') {
            return 'KANTOR';
        }

        return $name;
    }

    private function resolveShiftRule(array $shiftStats): array
    {
        $dominant = 'KANTOR';
        if ($shiftStats) {
            uasort($shiftStats, fn ($left, $right) => $right['count'] <=> $left['count']);
            $dominant = (string) array_key_first($shiftStats);
        }

        $catalog = $this->shiftRules()[$dominant] ?? [
            'title' => $dominant,
            'time_in' => '08:00',
            'time_out' => '17:00',
            'family' => 'operasional',
            'family_label' => 'Lainnya',
            'order' => 99,
            'subtitle' => 'Dibandingkan hanya dengan rekan yang shift-nya sama.',
        ];

        $observed = $shiftStats[$dominant] ?? [];
        $timeIn = $this->formatClock($this->toSeconds($observed['time_in'] ?? $catalog['time_in'])) ?: $catalog['time_in'];
        $timeOut = $this->formatClock($this->toSeconds($observed['time_out'] ?? $catalog['time_out'])) ?: $catalog['time_out'];

        return [
            'key' => $dominant,
            'title' => $catalog['title'],
            'family' => $catalog['family'],
            'order' => $catalog['order'],
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'schedule' => $timeIn . '–' . $timeOut,
            'subtitle' => $catalog['subtitle'],
        ];
    }

    private function buildClusters(Collection $employees): array
    {
        $clusters = [];
        $grouped = $employees->groupBy('cluster');
        $order = array_map(fn ($rule) => $rule['order'], $this->shiftRules());

        $keys = $grouped->keys()->sortBy(fn ($key) => $order[$key] ?? 99)->values();
        foreach ($keys as $key) {
            $members = $grouped->get($key)->values();
            if ($members->isEmpty()) {
                continue;
            }

            $sample = $members->first();
            $withIn = $members->filter(fn ($row) => $row['avg_in_delta'] !== null);
            $onTime = $withIn->filter(fn ($row) => $row['avg_in_delta'] <= 0);
            $lateIn = $withIn->filter(fn ($row) => $row['avg_in_delta'] > 0);
            $withOut = $members->filter(fn ($row) => $row['avg_out_delta'] !== null);
            $rule = $this->shiftRules()[$key] ?? [
                'family' => 'operasional',
                'family_label' => 'Lainnya',
                'subtitle' => $sample['cluster_label'] ?? '',
            ];

            $clusters[] = [
                'key' => $key,
                'family' => $rule['family'] ?? 'operasional',
                'family_label' => $rule['family_label'] ?? 'Lainnya',
                'title' => $sample['cluster_label'],
                'schedule' => ($sample['schedule_in'] ?? '-') . '–' . ($sample['schedule_out'] ?? '-'),
                'time_in' => $sample['schedule_in'] ?? null,
                'time_out' => $sample['schedule_out'] ?? null,
                'subtitle' => $rule['subtitle'] ?? '',
                'count' => $members->count(),
                'highlights' => [
                    // Tepat waktu = jam masuk atau lebih awal. Yang lewat jam aturan tidak masuk kategori ini.
                    // Paling tepat = paling dekat dengan jam masuk, tanpa melewatinya.
                    'on_time' => $onTime->sortByDesc('avg_in_delta')->first(),
                    'late_in' => $lateIn->sortByDesc('avg_in_delta')->first(),
                    'late_out' => $withOut->sortByDesc('avg_out_delta')->first(),
                ],
            ];
        }

        return $clusters;
    }

    private function formatDelta(?int $seconds, string $earlyPhrase, string $latePhrase): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $minutes = (int) round(abs($seconds) / 60);
        if ($minutes < 1) {
            return 'Tepat waktu';
        }

        $hours = intdiv($minutes, 60);
        $remain = $minutes % 60;
        if ($hours > 0 && $remain > 0) {
            $duration = $hours . ' jam ' . $remain . ' menit';
        } elseif ($hours > 0) {
            $duration = $hours . ' jam';
        } else {
            $duration = $remain . ' menit';
        }

        return $seconds < 0 ? $duration . ' ' . $earlyPhrase : $duration . ' ' . $latePhrase;
    }

    private function resolveInOut(Collection $today, Collection $tomorrow, $shift, $timeIn, $timeOut): array
    {
        $shiftName = strtoupper(trim((string) $shift));
        $todaySeconds = $this->punchSeconds($today);
        $nextSeconds = $this->punchSeconds($tomorrow);
        $timeInSeconds = $this->toSeconds($timeIn) ?? $this->toSeconds('08:00:00');
        $timeOutSeconds = $this->toSeconds($timeOut);
        $overnight = in_array($shiftName, ['24JAM', 'SHSECURITY2'], true)
            || ($timeInSeconds !== null && $timeOutSeconds !== null && $timeOutSeconds <= $timeInSeconds);

        if ($shiftName === 'SHSECURITY2') {
            $in = $todaySeconds->filter(fn ($value) => $value > $this->toSeconds('14:00:00'))->max();
            $out = $nextSeconds->first(fn ($value) => $value < $this->toSeconds('14:00:00'));
            return [$in ?: null, $out !== null ? $out + 86400 : null];
        }

        if ($overnight) {
            $in = $todaySeconds->min();
            $out = $nextSeconds->first(fn ($value) => $value < $this->toSeconds('14:00:00'));
            return [$in, $out !== null ? $out + 86400 : null];
        }

        $split = ($timeInSeconds ?? $this->toSeconds('08:00:00')) + (4 * 3600);
        $in = $todaySeconds->filter(fn ($value) => $value <= $split)->min();
        $out = $todaySeconds->filter(fn ($value) => $value > $split)->max();

        return [$in, $out];
    }

    private function punchSeconds(Collection $rows): Collection
    {
        return $rows->map(fn ($row) => $this->toSeconds($row->jam ?? null))->filter(fn ($value) => $value !== null)->sort()->values();
    }

    private function toSeconds($value): ?int
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '00:00:00') {
            return null;
        }

        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $match)) {
            return null;
        }

        return ((int) $match[1] * 3600) + ((int) $match[2] * 60) + (int) ($match[3] ?? 0);
    }

    private function averageSeconds(array $values): ?int
    {
        if (!$values) {
            return null;
        }

        return (int) round(array_sum($values) / count($values));
    }

    private function formatClock(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $normalized = $seconds % 86400;
        if ($normalized < 0) {
            $normalized += 86400;
        }

        return sprintf('%02d:%02d', intdiv($normalized, 3600), intdiv($normalized % 3600, 60));
    }

    private function presentEmployee($employee): ?array
    {
        if (!$employee) {
            return null;
        }

        $jabatan = $employee->jabatan_nama ?? $employee->jabatan ?: '-';

        return [
            'id' => (int) $employee->id,
            'name' => $employee->nama_lengkap,
            'nik' => $employee->nik_karyawan,
            'jabatan' => $jabatan,
            'image' => $employee->image,
            'department' => $employee->department,
        ];
    }

    private function emptyPayload($manager, array $range, string $period): array
    {
        return [
            'success' => true,
            'data' => [
                'manager' => $this->presentEmployee($manager),
                'period' => [
                    'type' => $period,
                    'label' => $range['label'],
                    'start' => $range['start']->toDateString(),
                    'end' => $range['end']->toDateString(),
                ],
                'summary' => ['total' => 0, 'with_attendance' => 0, 'cluster_count' => 0],
                'clusters' => [],
                'employees' => [],
            ],
        ];
    }
}
