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

class PengecekanKesehatanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = $this->baseQuery((int) $request->periode);

            if ($request->filled('check_date')) {
                $query->where('ehc.check_date', $request->check_date);
            }

            if ($request->filled('employee_id')) {
                $query->where('ehc.employee_id', (int) $request->employee_id);
            }

            $data = $query->get()->map(function ($item) {
                return $this->decorate($item);
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

    public function detail(Request $request)
    {
        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pemeriksaan kesehatan tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->decorate($record),
            ], 200);
        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function create(Request $request)
    {
        DB::beginTransaction();

        try {
            $validation = $this->validatePayload($request);
            if ($validation !== true) {
                DB::rollBack();
                return $validation;
            }

            $employeeId = (int) $request->employee_id;
            if (!$this->employeeExists($employeeId)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan',
                ], 404);
            }

            $payload = $this->buildPayload($request);
            $now = Carbon::now()->format('Y-m-d H:i:s');

            $record = EmployeeHealthCheck::create(array_merge($payload, [
                'employee_id' => $employeeId,
                'skk_number' => EmployeeHealthCheck::generateSkkNumber($payload['check_date']),
                'created_by' => $this->karyawan,
                'created_at' => $now,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
                'is_active' => 1,
            ]));

            DB::commit();

            $detail = $this->baseQuery((int) date('Y'))
                ->where('ehc.id', $record->id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Data pemeriksaan kesehatan berhasil disimpan',
                'data' => $this->decorate($detail),
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

    public function update(Request $request)
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

            $validation = $this->validatePayload($request, true);
            if ($validation !== true) {
                DB::rollBack();
                return $validation;
            }

            $employeeId = (int) ($request->employee_id ?: $record->employee_id);
            if (!$this->employeeExists($employeeId)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan',
                ], 404);
            }

            $payload = $this->buildPayload($request);
            $payload['employee_id'] = $employeeId;
            $payload['updated_by'] = $this->karyawan;
            $payload['updated_at'] = Carbon::now()->format('Y-m-d H:i:s');

            $record->update($payload);

            DB::commit();

            $detail = $this->baseQuery((int) date('Y'))
                ->where('ehc.id', $record->id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Data pemeriksaan kesehatan berhasil diperbarui',
                'data' => $this->decorate($detail),
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
        $item->keluhan = EmployeeHealthCheck::formatKeluhanDisplay($item->keluhan);
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

    private function buildPayload(Request $request)
    {
        $tensiSistolik = (int) $request->tensi_sistolik;
        $tensiDiastolik = (int) $request->tensi_diastolik;

        return [
            'check_date' => $request->check_date ?: Carbon::now()->format('Y-m-d'),
            'check_time' => $this->normalizeTime($request->check_time ?: Carbon::now()->format('H:i')),
            'tensi_sistolik' => $tensiSistolik,
            'tensi_diastolik' => $tensiDiastolik,
            'tensi_classification' => EmployeeHealthCheck::classifyTensi($tensiSistolik),
            'saturasi' => $this->parseDecimal($request->saturasi),
            'nadi' => (int) $request->nadi,
            'suhu' => $this->parseDecimal($request->suhu),
            'stetoskop' => $this->normalizeStetoskop($request->stetoskop),
            'gula_darah' => $this->nullableDecimal($request->gula_darah),
            'asam_urat' => $this->nullableDecimal($request->asam_urat),
            'kolesterol' => $this->nullableDecimal($request->kolesterol),
            'keterangan' => $this->normalizeKeterangan($request->keterangan),
            'keluhan' => EmployeeHealthCheck::normalizeKeluhanInput($request->keluhan),
        ];
    }

    private function validatePayload(Request $request, $isUpdate = false)
    {
        $employeeId = (int) $request->employee_id;
        if (!$isUpdate && $employeeId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan wajib dipilih',
            ], 422);
        }

        foreach (['tensi_sistolik' => 'Tensi sistolik', 'tensi_diastolik' => 'Tensi diastolik'] as $field => $label) {
            if (!$request->filled($field) && $request->input($field) !== '0' && $request->input($field) !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => $label . ' wajib diisi',
                ], 422);
            }

            if ((int) $request->input($field) <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => $label . ' harus lebih dari 0',
                ], 422);
            }
        }

        if ((int) $request->tensi_diastolik >= (int) $request->tensi_sistolik) {
            return response()->json([
                'success' => false,
                'message' => 'Tensi diastolik harus lebih kecil dari tensi sistolik',
            ], 422);
        }

        foreach (['saturasi' => 'Saturasi', 'nadi' => 'Nadi', 'suhu' => 'Suhu'] as $field => $label) {
            if ($request->input($field) === null || $request->input($field) === '') {
                return response()->json([
                    'success' => false,
                    'message' => $label . ' wajib diisi',
                ], 422);
            }
        }

        if ((int) $request->nadi <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Nadi harus lebih dari 0',
            ], 422);
        }

        $stetoskop = $this->normalizeStetoskop($request->stetoskop);
        if (!in_array($stetoskop, [EmployeeHealthCheck::STOK_NORMAL, EmployeeHealthCheck::STOK_ADA_KELAINAN], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Stetoskop wajib dipilih (normal / ada_kelainan)',
            ], 422);
        }

        $keterangan = $this->normalizeKeterangan($request->keterangan);
        $allowedKeterangan = [
            EmployeeHealthCheck::KET_SEHAT,
            EmployeeHealthCheck::KET_PERLU_OBSERVASI,
            EmployeeHealthCheck::KET_PERLU_RUJUKAN,
            EmployeeHealthCheck::KET_IZIN,
            EmployeeHealthCheck::KET_SAKIT,
            EmployeeHealthCheck::KET_LAINNYA,
        ];

        if (!in_array($keterangan, $allowedKeterangan, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Keterangan kesehatan wajib dipilih',
            ], 422);
        }

        if ($request->filled('check_date') && !Carbon::hasFormat($request->check_date, 'Y-m-d')) {
            return response()->json([
                'success' => false,
                'message' => 'Format tanggal pemeriksaan tidak valid',
            ], 422);
        }

        return true;
    }

    private function employeeExists($employeeId)
    {
        return MasterKaryawan::where('id', (int) $employeeId)->where('is_active', 1)->exists();
    }

    private function resolveId(Request $request)
    {
        $id = $request->input('id', $request->input('health_check_id'));

        return (int) $id;
    }

    private function normalizeTime($time)
    {
        $value = trim((string) $time);
        if ($value === '') {
            return Carbon::now()->format('H:i:s');
        }
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value . ':00';
        }

        return $value;
    }

    private function normalizeStetoskop($value)
    {
        $normalized = strtolower(trim(str_replace('-', '_', (string) $value)));
        if (in_array($normalized, ['normal', 'ada_kelainan', 'ada kelainan'], true)) {
            return $normalized === 'normal'
                ? EmployeeHealthCheck::STOK_NORMAL
                : EmployeeHealthCheck::STOK_ADA_KELAINAN;
        }

        return trim((string) $value);
    }

    private function normalizeKeterangan($value)
    {
        $normalized = strtolower(trim(str_replace('-', '_', (string) $value)));
        $map = [
            'sehat' => EmployeeHealthCheck::KET_SEHAT,
            'perlu observasi' => EmployeeHealthCheck::KET_PERLU_OBSERVASI,
            'perlu_observasi' => EmployeeHealthCheck::KET_PERLU_OBSERVASI,
            'perlu rujukan' => EmployeeHealthCheck::KET_PERLU_RUJUKAN,
            'perlu_rujukan' => EmployeeHealthCheck::KET_PERLU_RUJUKAN,
            'izin' => EmployeeHealthCheck::KET_IZIN,
            'sakit' => EmployeeHealthCheck::KET_SAKIT,
            'lainnya' => EmployeeHealthCheck::KET_LAINNYA,
        ];

        return $map[$normalized] ?? trim((string) $value);
    }

    private function parseDecimal($value)
    {
        return (float) str_replace(',', '.', (string) $value);
    }

    private function nullableDecimal($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->parseDecimal($value);
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
