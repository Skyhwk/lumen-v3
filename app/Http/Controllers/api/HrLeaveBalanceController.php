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

        return Datatables::of($query)
            ->addColumn('quota_days', function ($row) {
                return $this->rekapCached($row)['quota_days'];
            })
            ->addColumn('opening_used_days', function ($row) {
                return $this->rekapCached($row)['opening_used_days'];
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
            ->make(true);
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
            $entry = app(HrLeaveBalanceLedgerService::class)->recordManualAdjustment(
                $employee,
                $daysDelta,
                $notes,
                property_exists($this, 'karyawan') ? $this->karyawan : null,
                $this->nama_lengkap ?? 'HRD'
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

    public function voidLedger(Request $request)
    {
        $ledgerId = (int) $request->input('ledger_id', 0);
        $reason = trim((string) $request->input('void_reason', ''));

        try {
            $row = app(HrLeaveBalanceLedgerService::class)->voidEntry(
                $ledgerId,
                $reason,
                $this->nama_lengkap ?? 'HRD'
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
