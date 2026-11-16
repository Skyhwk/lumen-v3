<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DFUS;
use App\Models\MasterKaryawan;
use App\Services\SalesTeamHierarchyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SummaryDfusController extends Controller
{
    public function summaryDfus(Request $request)
    {
        try {
            [$startDate, $endDate] = $this->resolveSummaryDfusDateRange($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'success' => false], 422);
        }

        $viewer = MasterKaryawan::with('divisi')->find($this->user_id);
        if (!$viewer) {
            return response()->json(['message' => 'Data karyawan tidak ditemukan.', 'success' => false], 404);
        }

        $hierarchy = app(SalesTeamHierarchyService::class);
        $viewerMeta = $hierarchy->getViewerMeta($viewer);
        if (!$request->filled('mode')) {
            $request->merge([
                'mode' => $viewerMeta['default_mode'],
                'karyawan_id' => $viewerMeta['default_mode'] === 'single'
                    ? $viewerMeta['default_value']
                    : $request->karyawan_id,
            ]);
        }

        try {
            $this->assertSummaryDfusSelectionAllowed($request, $viewer);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Anda tidak memiliki akses ke data tersebut.',
                'success' => false,
            ], $e->getStatusCode());
        }

        $memberIds = $this->resolveSummaryDfusMemberIds($request, $viewer);
        $activeNames = DFUS::query()
            ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('sales_penanggung_jawab')
            ->distinct()
            ->pluck('sales_penanggung_jawab')
            ->filter()
            ->values()
            ->all();
        $activeNameSet = array_flip($activeNames);

        $hierarchyRows = $hierarchy->buildTableRows(function ($member) use ($activeNameSet) {
            if ((int) ($member->is_active ?? 0) === 1) {
                return true;
            }

            return isset($activeNameSet[(string) ($member->nama_lengkap ?? '')]);
        }, null, $memberIds);

        $rows = $this->buildSummaryDfusRows($startDate, $endDate, $hierarchyRows);
        $totals = $this->summarizeDfusRows($rows);
        $count = count($rows);
        $averages = [
            'total_call' => $count ? round($totals['total_call'] / $count, 1) : 0,
            'pic_contacted' => $count ? round($totals['pic_contacted'] / $count, 1) : 0,
            'avg_call_minutes' => $count
                ? round(array_sum(array_map(fn($row) => (float) ($row['avg_call_minutes'] ?? 0), $rows)) / $count, 1)
                : 0,
            'success_rate' => $count
                ? round(array_sum(array_map(fn($row) => (float) ($row['success_rate'] ?? 0), $rows)) / $count, 1)
                : 0,
        ];

        $nameToId = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['nama_lengkap'] ?? ''));
            if ($name !== '') {
                $nameToId[$name] = (int) ($row['karyawan_id'] ?? 0);
            }
        }

        return response()->json([
            'success' => true,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'allowed' => true,
            'viewer' => $viewerMeta,
            'data' => $rows,
            'hourly' => $this->summaryDfusHourly($nameToId, $startDate->toDateString(), $endDate->toDateString()),
            'totals' => $totals,
            'averages' => $averages,
        ], 200);
    }

    private function resolveSummaryDfusDateRange(Request $request): array
    {
        $periodType = in_array($request->period_type, ['daily', 'yearly', 'range'], true)
            ? $request->period_type
            : 'monthly';

        if ($periodType === 'daily') {
            $startDate = Carbon::parse($request->date ?: $request->start_date ?: Carbon::now())->startOfDay();
            return [$startDate, $startDate->copy()->endOfDay()];
        }

        if ($periodType === 'yearly') {
            $year = max(2024, (int) ($request->year ?: Carbon::now()->year));
            $startDate = Carbon::create($year, 1, 1)->startOfDay();
            return [$startDate, $startDate->copy()->endOfYear()];
        }

        if ($periodType === 'range') {
            $startDate = Carbon::parse($request->start_date ?: Carbon::now()->startOfMonth())->startOfDay();
            $endDate = Carbon::parse($request->end_date ?: Carbon::now())->endOfDay();
            if ($endDate->lt($startDate)) {
                throw new \InvalidArgumentException('Tanggal selesai harus sama atau setelah tanggal mulai.');
            }
            return [$startDate, $endDate];
        }

        $parts = explode(' ', (string) $request->periode);
        $monthMap = [
            'Januari' => '01', 'Februari' => '02', 'Maret' => '03', 'April' => '04',
            'Mei' => '05', 'Juni' => '06', 'Juli' => '07', 'Agustus' => '08',
            'September' => '09', 'Oktober' => '10', 'November' => '11', 'Desember' => '12',
        ];
        $periode = count($parts) === 2 && isset($monthMap[$parts[0]])
            ? $parts[1] . '-' . $monthMap[$parts[0]]
            : Carbon::now()->format('Y-m');

        $startDate = Carbon::createFromFormat('Y-m', $periode)->startOfMonth();
        return [$startDate, $startDate->copy()->endOfMonth()];
    }

    private function buildSummaryDfusRows(Carbon $startDate, Carbon $endDate, array $hierarchyRows): array
    {
        $salesIds = array_values(array_unique(array_filter(array_map(
            'intval',
            array_column($hierarchyRows, 'karyawan_id')
        ))));

        if (empty($salesIds)) {
            return [];
        }

        $sales = MasterKaryawan::whereIn('id', $salesIds)->pluck('nama_lengkap', 'id');
        $salesNames = $sales->filter()->values()->all();
        $statusRows = empty($salesNames)
            ? collect()
            : DFUS::whereIn('sales_penanggung_jawab', $salesNames)
                ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
                ->whereNotNull('keterangan')
                ->whereRaw("TRIM(keterangan) <> ''")
                ->selectRaw("sales_penanggung_jawab, UPPER(TRIM(keterangan)) as status, COUNT(*) as total")
                ->groupBy('sales_penanggung_jawab')
                ->groupByRaw("UPPER(TRIM(keterangan))")
                ->get();

        $statusesBySales = $statusRows->groupBy('sales_penanggung_jawab');
        $durationBySales = $this->summaryDfusDurationsBySales($salesIds, $startDate, $endDate);

        return array_map(function (array $row) use ($sales, $statusesBySales, $durationBySales) {
            $salesId = (int) $row['karyawan_id'];
            $name = $sales->get($salesId, $row['nama_lengkap'] ?? '');
            $statusCounts = $statusesBySales->get($name, collect())->pluck('total', 'status');
            $totalCalls = (int) $statusCounts->sum();
            $picContacted = (int) ($statusCounts->get('PIC', 0));
            $duration = $durationBySales->get($salesId);
            $durationCount = (int) ($duration->call_count ?? 0);
            $durationSeconds = (float) ($duration->total_seconds ?? 0);

            return array_merge($row, [
                'total_call' => $totalCalls,
                'pic_contacted' => $picContacted,
                'tidak_terhubung' => max(0, $totalCalls - $picContacted),
                'avg_call_minutes' => $durationCount > 0 ? round($durationSeconds / $durationCount / 60, 1) : 0,
                'avg_call_seconds' => $durationCount > 0 ? (int) round($durationSeconds / $durationCount) : 0,
                'duration_call_count' => $durationCount,
                'total_call_seconds' => $durationSeconds,
                'success_rate' => $totalCalls > 0 ? round(($picContacted / $totalCalls) * 100, 1) : 0,
            ]);
        }, $hierarchyRows);
    }

    private function summaryDfusHourly(array $nameToId, string $start, string $end): array
    {
        $names = array_values(array_filter(array_keys($nameToId)));
        if (empty($names)) {
            return [];
        }

        return DFUS::query()
            ->whereIn('sales_penanggung_jawab', $names)
            ->whereBetween('tanggal', [$start, $end])
            ->whereNotNull('keterangan')
            ->whereRaw("TRIM(keterangan) <> ''")
            ->selectRaw("
                sales_penanggung_jawab as sales_name,
                HOUR(jam) as hour,
                COUNT(*) as calls,
                SUM(CASE WHEN UPPER(TRIM(keterangan)) = 'PIC' THEN 1 ELSE 0 END) as connected
            ")
            ->groupBy('sales_penanggung_jawab')
            ->groupByRaw('HOUR(jam)')
            ->get()
            ->map(function ($row) use ($nameToId) {
                $karyawanId = (int) ($nameToId[$row->sales_name] ?? 0);
                if ($karyawanId <= 0) {
                    return null;
                }

                return [
                    'karyawan_id' => $karyawanId,
                    'hour' => (int) $row->hour,
                    'calls' => (int) $row->calls,
                    'connected' => (int) $row->connected,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function summaryDfusDurationsBySales(array $salesIds, Carbon $startDate, Carbon $endDate)
    {
        $timeToSeconds = "
            CASE
                WHEN LENGTH(time) - LENGTH(REPLACE(time, ':', '')) = 2 THEN
                    (CAST(SUBSTRING_INDEX(time, ':', 1) AS UNSIGNED) * 3600) +
                    (CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(time, ':', 2), ':', -1) AS UNSIGNED) * 60) +
                    (CAST(SUBSTRING_INDEX(time, ':', -1) AS UNSIGNED))
                WHEN LENGTH(time) - LENGTH(REPLACE(time, ':', '')) = 1 THEN
                    (CAST(SUBSTRING_INDEX(time, ':', 1) AS UNSIGNED) * 60) +
                    (CAST(SUBSTRING_INDEX(time, ':', -1) AS UNSIGNED))
                ELSE 0
            END
        ";

        return DB::table('log_webphone')
            ->whereIn('karyawan_id', $salesIds)
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString())
            ->whereNotNull('status_call')
            ->whereRaw("TRIM(status_call) <> ''")
            ->whereNotNull('time')
            ->where('time', '<>', '')
            ->selectRaw("karyawan_id, COUNT(*) as call_count, SUM({$timeToSeconds}) as total_seconds")
            ->groupBy('karyawan_id')
            ->get()
            ->keyBy('karyawan_id');
    }

    private function summarizeDfusRows(array $rows): array
    {
        $totalCall = array_sum(array_column($rows, 'total_call'));
        $picContacted = array_sum(array_column($rows, 'pic_contacted'));
        $durationCount = array_sum(array_column($rows, 'duration_call_count'));
        $durationSeconds = array_sum(array_column($rows, 'total_call_seconds'));

        return [
            'total_call' => $totalCall,
            'pic_contacted' => $picContacted,
            'avg_call_minutes' => $durationCount > 0 ? round($durationSeconds / $durationCount / 60, 1) : 0,
            'success_rate' => $totalCall > 0 ? round(($picContacted / $totalCall) * 100, 1) : 0,
        ];
    }

    public function summaryDfusDetail(Request $request)
    {
        try {
            [$startDate, $endDate] = $this->resolveSummaryDfusDateRange($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'success' => false], 422);
        }

        $viewer = MasterKaryawan::select('id', 'id_jabatan', 'id_department', 'grade', 'nama_lengkap')->find($this->user_id);
        if (!$viewer) {
            return response()->json(['message' => 'Data karyawan tidak ditemukan.', 'success' => false], 404);
        }

        $karyawanId = (int) $request->karyawan_id;
        if ($karyawanId <= 0) {
            return response()->json(['message' => 'Sales tidak valid.', 'success' => false], 422);
        }

        $sales = MasterKaryawan::select('id', 'nama_lengkap')->find($karyawanId);
        if (!$sales) {
            return response()->json(['message' => 'Data sales tidak ditemukan.', 'success' => false], 404);
        }

        if (!$this->canViewSummaryDfusSales($viewer, $karyawanId)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke data sales ini.', 'success' => false], 403);
        }

        $start = $startDate->toDateString();
        $end = $endDate->toDateString();
        $perPage = max(1, min(50, (int) ($request->input('length') ?: $request->input('per_page') ?: 15)));
        $offset = $request->exists('start')
            ? max(0, (int) $request->input('start', 0))
            : (max(1, (int) $request->input('page', 1)) - 1) * $perPage;
        $baseQuery = DFUS::query()
            ->from('dfus')
            ->leftJoin('master_pelanggan as p', 'p.id_pelanggan', '=', 'dfus.id_pelanggan')
            ->where('dfus.sales_penanggung_jawab', $sales->nama_lengkap)
            ->whereBetween('dfus.tanggal', [$start, $end])
            ->whereNotNull('dfus.keterangan')
            ->whereRaw("TRIM(dfus.keterangan) <> ''");

        $recordsTotal = (clone $baseQuery)->count('dfus.id');

        $this->applySummaryDfusDetailColumnFilters($baseQuery, $request);

        $recordsFiltered = (clone $baseQuery)->count('dfus.id');
        $lastPage = max(1, (int) ceil($recordsFiltered / $perPage));
        if ($offset >= $recordsFiltered && $recordsFiltered > 0) {
            $offset = ($lastPage - 1) * $perPage;
        }

        $dfusRows = $recordsFiltered === 0
            ? collect()
            : $baseQuery
                ->orderByDesc('dfus.tanggal')
                ->orderByDesc('dfus.jam')
                ->offset($offset)
                ->limit($perPage)
                ->get([
                    'dfus.id',
                    'dfus.tanggal',
                    'dfus.jam',
                    'dfus.id_pelanggan',
                    'dfus.kontak',
                    'dfus.no_pic',
                    'dfus.pic_pelanggan',
                    'dfus.keterangan',
                    'dfus.keterangan_activity',
                    'p.nama_pelanggan as nama_perusahaan',
                ]);

        $logsByDate = $this->summaryDfusLogsForBatch($karyawanId, $dfusRows);
        $data = $dfusRows->map(function ($row) use ($logsByDate) {
            $status = strtoupper(trim((string) $row->keterangan));
            $matchedLog = $this->matchSummaryDfusCallLog(
                $logsByDate->get($row->tanggal, collect()),
                $row->kontak,
                $row->no_pic
            );
            $durationTime = $matchedLog->time ?? null;
            $activity = trim((string) ($row->keterangan_activity ?? ''));

            return [
                'id' => $row->id,
                'tanggal' => $row->tanggal,
                'jam' => $row->jam,
                'id_pelanggan' => $row->id_pelanggan,
                'nama_perusahaan' => $row->nama_perusahaan ?: '-',
                'pic_pelanggan' => $row->pic_pelanggan ?: '-',
                'status_call' => $status !== '' ? $status : '-',
                'pic_contacted' => $status === 'PIC',
                'lama_panggilan' => $this->formatCallDuration($durationTime),
                'lama_panggilan_seconds' => $this->webphoneTimeToSeconds($durationTime),
                'keterangan_activity' => $activity !== '' ? $activity : '-',
                'catatan' => $activity !== '' ? $activity : '-',
            ];
        })->values();

        $from = $recordsFiltered === 0 ? 0 : $offset + 1;
        $to = min($offset + $perPage, $recordsFiltered);

        return response()->json([
            'success' => true,
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'sales' => [
                'karyawan_id' => $sales->id,
                'nama_lengkap' => $sales->nama_lengkap,
            ],
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'data' => $data,
            'meta' => [
                'page' => (int) floor($offset / $perPage) + 1,
                'per_page' => $perPage,
                'total' => $recordsFiltered,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ],
        ], 200);
    }

    private function columnSearchValue(Request $request, int $index): string
    {
        $columns = $request->input('columns', []);
        if (!is_array($columns) || !isset($columns[$index]) || !is_array($columns[$index])) {
            return '';
        }

        return trim((string) ($columns[$index]['search']['value'] ?? ''));
    }

    private function applySummaryDfusDetailColumnFilters($query, Request $request): void
    {
        $company = $this->columnSearchValue($request, 1);
        if ($company !== '') {
            $like = '%' . $company . '%';
            $query->where(function ($inner) use ($like) {
                $inner->where('p.nama_pelanggan', 'like', $like)
                    ->orWhere('dfus.id_pelanggan', 'like', $like);
            });
        }

        $picName = $this->columnSearchValue($request, 2);
        if ($picName !== '') {
            $query->where('dfus.pic_pelanggan', 'like', '%' . $picName . '%');
        }

        $tanggal = $this->columnSearchValue($request, 3);
        if ($tanggal !== '') {
            $query->where('dfus.tanggal', 'like', '%' . $tanggal . '%');
        }

        $jam = $this->columnSearchValue($request, 4);
        if ($jam !== '') {
            $query->where('dfus.jam', 'like', '%' . $jam . '%');
        }

        $status = strtolower($this->columnSearchValue($request, 6));
        if ($status === 'ya' || $status === 'pic') {
            $query->whereRaw("UPPER(TRIM(COALESCE(dfus.keterangan, ''))) = 'PIC'");
        } elseif ($status === 'tidak') {
            $query->whereRaw("UPPER(TRIM(COALESCE(dfus.keterangan, ''))) <> 'PIC'");
        } elseif ($status !== '') {
            $query->where('dfus.keterangan', 'like', '%' . $status . '%');
        }

        $catatan = $this->columnSearchValue($request, 7);
        if ($catatan !== '') {
            $query->where('dfus.keterangan_activity', 'like', '%' . $catatan . '%');
        }
    }

    private function viewerIsManagerGrade(MasterKaryawan $viewer): bool
    {
        $grade = strtoupper(trim((string) ($viewer->grade ?? '')));

        return in_array($grade, ['MANAGER', 'SENIOR MANAGER'], true);
    }

    private function viewerBelongsToDfusDivision(MasterKaryawan $viewer, ?SalesTeamHierarchyService $hierarchy = null): bool
    {
        $hierarchy = $hierarchy ?: app(SalesTeamHierarchyService::class);

        if (in_array((int) $viewer->id, $hierarchy->salesExecutiveIds(), true)) {
            return true;
        }

        if ($hierarchy->isSalesStaff($viewer)) {
            return true;
        }

        if ((int) $viewer->id_jabatan === 21) {
            return true;
        }

        $viewer->loadMissing('divisi');
        $divisiName = strtoupper(trim((string) optional($viewer->divisi)->nama_divisi));
        if ($divisiName === '') {
            return false;
        }

        return str_contains($divisiName, 'SALES')
            || str_contains($divisiName, 'PENJUALAN')
            || str_contains($divisiName, 'MARKETING SALES');
    }

    private function emptySummaryDfusResponse(string $start, string $end)
    {
        return response()->json([
            'success' => true,
            'allowed' => false,
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'can_filter_sales' => false,
            'data' => [],
            'hourly' => [],
            'totals' => [
                'total_call' => 0,
                'pic_contacted' => 0,
                'avg_call_minutes' => 0,
                'success_rate' => 0,
            ],
            'averages' => [
                'total_call' => 0,
                'pic_contacted' => 0,
                'avg_call_minutes' => 0,
                'success_rate' => 0,
            ],
        ], 200);
    }

    private function canViewSummaryDfusSales(MasterKaryawan $viewer, int $karyawanId): bool
    {
        $hierarchy = app(SalesTeamHierarchyService::class);

        return $karyawanId > 0
            && $hierarchy->viewerCanUseSelection($viewer, 'single', (string) $karyawanId);
    }

    private function assertSummaryDfusSelectionAllowed(Request $request, MasterKaryawan $viewer): void
    {
        $mode = (string) ($request->mode ?: 'all');
        $hierarchy = app(SalesTeamHierarchyService::class);

        if (!$hierarchy->viewerCanUseSelection($viewer, $mode, $request->karyawan_id)) {
            abort(403, 'Anda tidak memiliki akses ke data tersebut.');
        }
    }

    private function resolveSummaryDfusMemberIds(Request $request, MasterKaryawan $viewer): array
    {
        $hierarchy = app(SalesTeamHierarchyService::class);
        $mode = (string) ($request->mode ?: 'all');

        if ($mode === 'all') {
            return $hierarchy->resolveViewerScopeMemberIds($viewer);
        }

        if ($mode === 'team' && $request->karyawan_id) {
            return $hierarchy->resolveDescendantIds((int) str_replace('team_', '', $request->karyawan_id));
        }

        if ($mode === 'single' && $request->karyawan_id) {
            $karyawanId = (int) $request->karyawan_id;
            $member = MasterKaryawan::find($karyawanId);

            return (in_array($karyawanId, $hierarchy->salesExecutiveIds(), true)
                || ($member && $hierarchy->isSalesStaff($member)))
                ? [$karyawanId]
                : $hierarchy->resolveDescendantIds($karyawanId);
        }

        return $hierarchy->resolveViewerScopeMemberIds($viewer);
    }

    private function summaryDfusLogsForBatch(int $karyawanId, $dfusRows)
    {
        if ($dfusRows->isEmpty()) {
            return collect();
        }

        $dates = $dfusRows->pluck('tanggal')->filter()->unique()->values();
        if ($dates->isEmpty()) {
            return collect();
        }

        return DB::table('log_webphone')
            ->where('karyawan_id', $karyawanId)
            ->where(function ($query) use ($dates) {
                foreach ($dates as $date) {
                    $query->orWhereBetween('created_at', [$date . ' 00:00:00', $date . ' 23:59:59']);
                }
            })
            ->orderByDesc('created_at')
            ->get(['number', 'time', 'created_at'])
            ->groupBy(function ($log) {
                return Carbon::parse($log->created_at)->toDateString();
            });
    }

    private function matchSummaryDfusCallLog($dayLogs, $kontak, $noPic)
    {
        if (!$dayLogs || $dayLogs->isEmpty()) {
            return null;
        }

        $phones = array_values(array_filter([
            $this->normalizePhoneDigits($kontak),
            $this->normalizePhoneDigits(is_string($kontak) && strpos($kontak, ' - ') !== false ? explode(' - ', $kontak, 2)[1] : null),
            $this->normalizePhoneDigits($noPic),
        ]));

        $matched = $dayLogs->filter(function ($log) use ($phones) {
            $number = $this->normalizePhoneDigits($log->number);
            if ($number === '') {
                return false;
            }
            foreach ($phones as $phone) {
                if ($phone !== '' && (strpos($number, $phone) !== false || strpos($phone, $number) !== false)) {
                    return true;
                }
            }
            return false;
        });

        if ($matched->isEmpty()) {
            return null;
        }

        return $matched->sortByDesc(function ($log) {
            return $this->webphoneTimeToSeconds($log->time ?? null);
        })->first();
    }

    private function normalizePhoneDigits($value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if ($digits === '') {
            return '';
        }
        if (strpos($digits, '62') === 0) {
            $digits = '0' . substr($digits, 2);
        }
        return $digits;
    }

    private function webphoneTimeToSeconds(?string $time): int
    {
        if (!$time) {
            return 0;
        }
        $parts = array_map('intval', explode(':', $time));
        if (count($parts) === 3) {
            return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
        }
        if (count($parts) === 2) {
            return ($parts[0] * 60) + $parts[1];
        }
        return (int) $time;
    }

    private function formatCallDuration(?string $time): string
    {
        $seconds = $this->webphoneTimeToSeconds($time);
        if ($seconds <= 0) {
            return '-';
        }
        $minutes = intdiv($seconds, 60);
        $remain = $seconds % 60;
        if ($minutes > 0 && $remain > 0) {
            return $minutes . ' menit ' . $remain . ' detik';
        }
        if ($minutes > 0) {
            return $minutes . ' menit';
        }
        return $remain . ' detik';
    }
}

