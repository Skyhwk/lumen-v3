<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeHealthCheck;
use App\Models\MasterKaryawan;
use App\Services\EmployeeHealthCheckDocumentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class DataKesehatanKaryawanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $periode = (int) ($request->periode ?: date('Y'));

            $latestCheckSub = DB::table('employee_health_checks as lc')
                ->select('lc.employee_id', DB::raw('MAX(lc.id) as latest_id'))
                ->where('lc.is_active', 1)
                ->when($periode >= 2000, function ($query) use ($periode) {
                    $query->whereYear('lc.check_date', $periode);
                })
                ->groupBy('lc.employee_id');

            $query = MasterKaryawan::query()
                ->from('master_karyawan as mk')
                ->leftJoin('master_divisi as d', 'mk.id_department', '=', 'd.id')
                ->leftJoinSub($latestCheckSub, 'latest_map', function ($join) {
                    $join->on('mk.id', '=', 'latest_map.employee_id');
                })
                ->leftJoin('employee_health_checks as ehc', 'ehc.id', '=', 'latest_map.latest_id')
                ->where('mk.is_active', 1)
                ->select(
                    'mk.id as employee_id',
                    'mk.nik_karyawan',
                    'mk.nama_lengkap as karyawan',
                    'd.nama_divisi',
                    'ehc.id as last_check_id',
                    'ehc.check_date as last_check_date',
                    'ehc.check_time as last_check_time',
                    'ehc.keterangan as last_keterangan',
                    'ehc.tensi_sistolik',
                    'ehc.tensi_diastolik',
                    'ehc.tensi_classification',
                    DB::raw('CASE WHEN ehc.id IS NULL THEN 0 ELSE 1 END as has_health_data')
                );

            $data = $query->get()->map(function ($item) {
                $item->has_health_data = (int) $item->has_health_data === 1;
                $item->health_status_label = $item->has_health_data
                    ? 'Sudah Ada Data'
                    : 'Belum Ada Data';
                $item->last_keterangan_label = $this->mapKeteranganLabel($item->last_keterangan);
                $item->last_check_date_label = $item->last_check_date
                    ? Carbon::parse($item->last_check_date)->format('d-m-Y')
                    : '-';
                $item->last_check_time_label = $item->last_check_time
                    ? substr((string) $item->last_check_time, 0, 5)
                    : '-';
                $item->tensi_label = EmployeeHealthCheck::formatTensiLabel(
                    $item->tensi_sistolik ?? null,
                    $item->tensi_diastolik ?? null
                );

                return $item;
            });

            return Datatables::of($data)->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function history(Request $request)
    {
        try {
            $employeeId = (int) ($request->employee_id ?: $request->id);
            if ($employeeId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID karyawan wajib diisi',
                ], 422);
            }

            $karyawan = MasterKaryawan::where('id', $employeeId)->where('is_active', 1)->first();
            if ($karyawan === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan',
                ], 404);
            }

            $periode = (int) ($request->periode ?: date('Y'));
            $query = $this->baseQuery($periode)->where('ehc.employee_id', $employeeId);

            $records = $query
                ->orderBy('ehc.check_date', 'desc')
                ->orderBy('ehc.check_time', 'desc')
                ->get()
                ->map(function ($item) {
                    return $this->decorate($item);
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => [
                        'id' => $karyawan->id,
                        'nik_karyawan' => $karyawan->nik_karyawan,
                        'nama_lengkap' => $karyawan->nama_lengkap,
                        'nama_divisi' => optional($karyawan->divisi)->nama_divisi ?: '-',
                    ],
                    'records' => $records,
                    'total' => $records->count(),
                ],
            ], 200);
        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        DB::beginTransaction();

        try {
            $record = EmployeeHealthCheck::where('id', $this->resolveId($request))
                ->where('is_active', 1)
                ->first();

            if ($record === null) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data pemeriksaan kesehatan tidak ditemukan',
                ], 404);
            }

            $now = Carbon::now()->format('Y-m-d H:i:s');
            $record->update([
                'is_active' => 0,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data pemeriksaan kesehatan berhasil dihapus',
            ], 200);
        } catch (\Exception $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => $ex->getMessage(),
            ], 500);
        }
    }

    public function printSkk(Request $request, EmployeeHealthCheckDocumentService $documentService)
    {
        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pemeriksaan kesehatan tidak ditemukan',
                ], 404);
            }

            $record = $this->ensureSkkNumber($record);

            $pdfString = $documentService->generateSkkPdf($record);

            return response()->json([
                'success' => true,
                'message' => 'Surat Keterangan Kesehatan berhasil dibuat',
                'data' => base64_encode($pdfString),
                'pdf_ready' => true,
            ], 200);
        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    private function baseQuery($periode)
    {
        $query = EmployeeHealthCheck::query()
            ->from('employee_health_checks as ehc')
            ->leftJoin('master_karyawan as u', 'ehc.employee_id', '=', 'u.id')
            ->leftJoin('master_divisi as d', 'u.id_department', '=', 'd.id')
            ->where('ehc.is_active', 1)
            ->select(
                'ehc.id',
                'ehc.employee_id',
                'ehc.check_date',
                'ehc.check_time',
                'ehc.tensi_sistolik',
                'ehc.tensi_diastolik',
                'ehc.tensi_classification',
                'ehc.saturasi',
                'ehc.nadi',
                'ehc.suhu',
                'ehc.stetoskop',
                'ehc.gula_darah',
                'ehc.asam_urat',
                'ehc.kolesterol',
                'ehc.keterangan',
                'ehc.keluhan',
                'ehc.skk_number',
                'ehc.created_by',
                'ehc.created_at',
                'ehc.updated_by',
                'ehc.updated_at',
                'u.nik_karyawan',
                'u.nama_lengkap as karyawan',
                'd.nama_divisi'
            );

        $periode = (int) $periode;
        if ($periode >= 2000) {
            $query->whereYear('ehc.check_date', $periode);
        }

        return $query;
    }

    private function findActiveRecord($id)
    {
        if ((int) $id <= 0) {
            return null;
        }

        return $this->baseQuery(0)
            ->where('ehc.id', (int) $id)
            ->first();
    }

    private function decorate($item)
    {
        if ($item === null) {
            return null;
        }

        $item->karyawan = $item->karyawan ?: '-';
        $item->nama_divisi = $item->nama_divisi ?: '-';
        $item->keterangan_label = $this->mapKeteranganLabel($item->keterangan);
        $item->stetoskop_label = $this->mapStetoskopLabel($item->stetoskop);
        $item->check_date_label = $item->check_date
            ? Carbon::parse($item->check_date)->format('d-m-Y')
            : '-';
        $item->check_time_label = $item->check_time
            ? substr((string) $item->check_time, 0, 5)
            : '-';
        $item->gula_darah_label = $this->formatOptionalNumber($item->gula_darah);
        $item->asam_urat_label = $this->formatOptionalNumber($item->asam_urat);
        $item->kolesterol_label = $this->formatOptionalNumber($item->kolesterol);
        $item->keluhan = $item->keluhan ?: '-';
        $item->skk_number = $item->skk_number ?: '-';
        $item->tensi_label = EmployeeHealthCheck::formatTensiLabel(
            $item->tensi_sistolik ?? null,
            $item->tensi_diastolik ?? null
        );

        return $item;
    }

    private function ensureSkkNumber($record)
    {
        $existingNumber = trim((string) ($record->skk_number ?? ''));
        if ($existingNumber !== '' && $existingNumber !== '-') {
            return $record;
        }

        $skkNumber = EmployeeHealthCheck::generateSkkNumber($record->check_date);
        $now = Carbon::now()->format('Y-m-d H:i:s');

        EmployeeHealthCheck::where('id', $record->id)->update([
            'skk_number' => $skkNumber,
            'updated_by' => $this->karyawan,
            'updated_at' => $now,
        ]);

        $record->skk_number = $skkNumber;

        return $record;
    }

    private function resolveId(Request $request)
    {
        $id = $request->input('id', $request->input('health_check_id'));

        return (int) $id;
    }

    private function formatOptionalNumber($value)
    {
        return ($value === null || $value === '') ? '-' : $value;
    }

    private function mapKeteranganLabel($value)
    {
        $map = [
            EmployeeHealthCheck::KET_SEHAT => 'Sehat',
            EmployeeHealthCheck::KET_PERLU_OBSERVASI => 'Perlu Observasi',
            EmployeeHealthCheck::KET_PERLU_RUJUKAN => 'Perlu Rujukan',
            EmployeeHealthCheck::KET_IZIN => 'Izin',
            EmployeeHealthCheck::KET_SAKIT => 'Sakit',
            EmployeeHealthCheck::KET_LAINNYA => 'Lainnya',
        ];

        return $map[$value] ?? ($value ?: '-');
    }

    private function mapStetoskopLabel($value)
    {
        $map = [
            EmployeeHealthCheck::STOK_NORMAL => 'Normal',
            EmployeeHealthCheck::STOK_ADA_KELAINAN => 'Ada Kelainan',
        ];

        return $map[$value] ?? ($value ?: '-');
    }
}
