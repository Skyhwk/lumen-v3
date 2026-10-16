<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Services\Greatday\LeaveBalanceService;
use App\Services\Hr\HrLeaveBalanceLedgerService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Yajra\Datatables\Datatables;

class HrLeaveBalanceController extends Controller
{
    /** @var array<int, array<string, mixed>> */
    private static $rekapMemo = [];

    public function index(Request $request)
    {
        self::$rekapMemo = [];

        $query = MasterKaryawan::query()
            ->where('is_active', true)
            ->with(['department', 'divisi'])
            ->orderBy('nik_karyawan');

        if ($request->filled('status_karyawan')) {
            $query->where('status_karyawan', $request->input('status_karyawan'));
        }

        $this->applyRekapSaldoEmployeeFilters($query, $request);

        return Datatables::of($query)
            ->filter(function ($filteredQuery) use ($request) {
                $this->applyRekapSaldoEmployeeFilters($filteredQuery, $request);
            })
            ->addColumn('quota_days', function ($row) {
                return $this->rekapCached($row)['quota_days'];
            })
            ->addColumn('opening_used_days', function ($row) {
                return $this->rekapCached($row)['opening_used_days'];
            })
            ->addColumn('manual_used_days', function ($row) {
                return $this->rekapCached($row)['manual_used_days'];
            })
            ->addColumn('system_used_days', function ($row) {
                return $this->rekapCached($row)['system_used_days'];
            })
            ->addColumn('pending_used_days', function ($row) {
                return $this->rekapCached($row)['pending_used_days'];
            })
            ->addColumn('ledger_used_days', function ($row) {
                return $this->rekapCached($row)['ledger_used_days'];
            })
            ->addColumn('alpa_days', function ($row) {
                return $this->rekapCached($row)['alpa_days'];
            })
            ->addColumn('used_days', function ($row) {
                return $this->rekapCached($row)['used_days'];
            })
            ->addColumn('remaining_days', function ($row) {
                return $this->rekapCached($row)['remaining_days'];
            })
            ->addColumn('period_start', function ($row) {
                return $this->rekapCached($row)['period_start'];
            })
            ->addColumn('period_end', function ($row) {
                return $this->rekapCached($row)['period_end'];
            })
            ->addColumn('organization_unit', function ($row) {
                return $this->rekapCached($row)['organization_unit'];
            })
            ->addColumn('eligible', function ($row) {
                return $this->rekapCached($row)['eligible'];
            })
            ->orderColumn('nik_karyawan', 'nik_karyawan $1')
            ->orderColumn('nama_lengkap', 'nama_lengkap $1')
            ->orderColumn('status_karyawan', 'status_karyawan $1')
            ->filterColumn('nik_karyawan', function ($query, $keyword) {
                $query->where('nik_karyawan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_lengkap', function ($query, $keyword) {
                $query->where('nama_lengkap', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('status_karyawan', function ($query, $keyword) {
                $query->where('status_karyawan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('organization_unit', function ($query, $keyword) {
                $query->whereHas('department', function ($q) use ($keyword) {
                    $q->where('nama_divisi', 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function applyRekapSaldoEmployeeFilters($query, Request $request): void
    {
        $nik = $this->resolveFilterKeyword($request, 'filter_nik', 'nik_karyawan', 2);
        if ($nik !== '') {
            $query->where('nik_karyawan', 'like', '%' . $nik . '%');
        }

        $nama = $this->resolveFilterKeyword($request, 'filter_nama', 'nama_lengkap', 3);
        if ($nama !== '') {
            $query->where('nama_lengkap', 'like', '%' . $nama . '%');
        }

        $divisi = $this->resolveFilterKeyword($request, 'filter_divisi', 'organization_unit', 4);
        if ($divisi !== '') {
            $query->whereHas('department', function ($q) use ($divisi) {
                $q->where('nama_divisi', 'like', '%' . $divisi . '%');
            });
        }

        $status = $this->resolveFilterKeyword($request, 'filter_status', 'status_karyawan', 5);
        if ($status !== '') {
            $query->where('status_karyawan', 'like', '%' . $status . '%');
        }
    }

    private function resolveFilterKeyword(Request $request, string $flatKey, string $columnData, int $columnIndex): string
    {
        $flat = trim((string) $request->input($flatKey, ''));
        if ($flat !== '') {
            return $flat;
        }

        $byData = $this->columnSearchValue($request, $columnData);
        if ($byData !== '') {
            return $byData;
        }

        return $this->columnSearchValueByIndex($request, $columnIndex);
    }

    private function columnSearchValue(Request $request, string $columnData): string
    {
        $columns = $request->input('columns', []);
        if (!is_array($columns)) {
            return '';
        }

        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $data = $column['data'] ?? null;
            $name = $column['name'] ?? null;
            if ($data === $columnData || $name === $columnData) {
                return trim((string) ($column['search']['value'] ?? ''));
            }
        }

        return '';
    }

    private function columnSearchValueByIndex(Request $request, int $columnIndex): string
    {
        $columns = $request->input('columns', []);
        if (!is_array($columns) || !isset($columns[$columnIndex])) {
            return '';
        }

        $column = $columns[$columnIndex];
        if (!is_array($column)) {
            return '';
        }

        return trim((string) ($column['search']['value'] ?? ''));
    }

    public function show(Request $request)
    {
        $id = (int) $request->input('karyawan_id', $request->input('id', 0));
        if ($id <= 0) {
            return $this->emptyShowPayload('Belum ada data');
        }

        $employee = MasterKaryawan::query()->where('is_active', true)->find($id);
        if (!$employee) {
            return $this->emptyShowPayload('Belum ada data');
        }

        try {
            $service = app(LeaveBalanceService::class);
            $page = max(1, (int) $request->input('page', 1));
            $perPage = min(50, max(1, (int) $request->input('per_page', 20)));
            $period = $service->ensureActivePeriod($employee);

            return response()->json([
                'data' => [
                    'summary' => $service->summary($employee),
                    'usage' => $service->usageLedger($employee, $page, $perPage),
                    'ledger' => app(HrLeaveBalanceLedgerService::class)->ledgerRowsForPeriod($period),
                ],
                'message' => 'OK',
            ], 200);
        } catch (\Throwable $e) {
            return $this->emptyShowPayload('Belum ada data');
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    private function emptyShowPayload(string $message)
    {
        return response()->json([
            'data' => [
                'summary' => null,
                'usage' => ['items' => [], 'has_more' => false],
                'ledger' => [],
            ],
            'message' => $message,
        ], 200);
    }

    /**
     * @param MasterKaryawan $row
     * @return array<string, mixed>
     */
    private function rekapCached($row): array
    {
        $key = (int) $row->id;
        if (array_key_exists($key, self::$rekapMemo)) {
            return self::$rekapMemo[$key];
        }

        try {
            self::$rekapMemo[$key] = app(LeaveBalanceService::class)->rekapRow($row);
        } catch (\Throwable $e) {
            self::$rekapMemo[$key] = [
                'quota_days' => 0,
                'opening_used_days' => 0,
                'manual_used_days' => 0,
                'system_used_days' => 0,
                'pending_used_days' => 0,
                'ledger_used_days' => 0,
                'alpa_days' => 0,
                'used_days' => 0,
                'remaining_days' => 0,
                'period_start' => null,
                'period_end' => null,
                'organization_unit' => optional($row->department)->nama_divisi ?: optional($row->divisi)->nama_divisi ?: '',
                'eligible' => false,
            ];
        }

        return self::$rekapMemo[$key];
    }

    /**
     * @return array{0: ?MasterKaryawan, 1: string}
     */
    private function ledgerActorContext(): array
    {
        $actor = $this->user_id
            ? MasterKaryawan::query()->find($this->user_id)
            : null;
        $name = trim((string) ($this->karyawan ?? ''));
        if ($name === '') {
            $name = $actor ? $actor->nama_lengkap : 'HRD';
        }

        return [$actor, $name];
    }

    public function storeLedger(Request $request)
    {
        $karyawanId = (int) $request->input('karyawan_id', 0);
        $daysDelta = (int) $request->input('days_delta', 0);
        $notes = trim((string) $request->input('notes', ''));

        $employee = MasterKaryawan::query()->where('is_active', true)->find($karyawanId);
        if (!$employee) {
            return response()->json(['message' => 'Karyawan tidak ditemukan'], 404);
        }

        try {
            [$actor, $actorName] = $this->ledgerActorContext();
            $entry = app(HrLeaveBalanceLedgerService::class)->recordManualAdjustment(
                $employee,
                $daysDelta,
                $notes,
                $actor,
                $actorName
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $service = app(LeaveBalanceService::class);

        return response()->json([
            'message' => 'Koreksi saldo cuti berhasil disimpan',
            'data' => [
                'ledger_id' => $entry->id,
                'summary' => $service->summary($employee),
            ],
        ], 201);
    }

    public function reclassifyAlpaLedger(Request $request)
    {
        $ledgerId = (int) $request->input('ledger_id', 0);
        $targetType = trim((string) $request->input('target_type', ''));
        $notes = trim((string) $request->input('notes', ''));
        $permissionKind = trim((string) $request->input('permission_kind', 'event'));

        try {
            [$actor, $actorName] = $this->ledgerActorContext();
            $result = app(HrLeaveBalanceLedgerService::class)->reclassifyAlpaEntry(
                $ledgerId,
                $targetType,
                $notes,
                $actor,
                $actorName,
                $permissionKind !== '' ? $permissionKind : 'event'
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $employee = MasterKaryawan::find($result['voided']->karyawan_id);
        $summary = $employee ? app(LeaveBalanceService::class)->summary($employee) : null;

        return response()->json([
            'message' => 'Koreksi alpa berhasil disimpan',
            'data' => [
                'summary' => $summary,
                'target' => $result['target'],
                'hr_request_id' => $result['hr_request'] ? $result['hr_request']->id : null,
            ],
        ], 200);
    }

    public function voidLedger(Request $request)
    {
        $ledgerId = (int) $request->input('ledger_id', 0);
        $reason = trim((string) $request->input('void_reason', ''));

        try {
            [, $actorName] = $this->ledgerActorContext();
            $row = app(HrLeaveBalanceLedgerService::class)->voidEntry(
                $ledgerId,
                $reason,
                $actorName
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $employee = MasterKaryawan::find($row->karyawan_id);
        $summary = $employee ? app(LeaveBalanceService::class)->summary($employee) : null;

        return response()->json([
            'message' => 'Entri ledger dibatalkan',
            'data' => ['summary' => $summary],
        ], 200);
    }
}
