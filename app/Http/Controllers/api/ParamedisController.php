<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeHealthCheck;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class ParamedisController extends Controller
{
    /**
     * Pengecekan Kesehatan — daftar pemeriksaan harian.
     */
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

    /**
     * Data Kesehatan Karyawan — daftar karyawan + status data kesehatan.
     */
    public function indexKaryawan(Request $request)
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
                    'ehc.tensi',
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

    /**
     * Riwayat kesehatan satu karyawan (terbaru → terlama).
     */
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

    /**
     * Cetak Surat Keterangan Kesehatan — generate nomor surat, template PDF menyusul.
     */
    public function printSkk(Request $request)
    {
        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pemeriksaan kesehatan tidak ditemukan',
                ], 404);
            }

            if (!$record->skk_number) {
                $skkNumber = $this->generateSkkNumber($record);
                EmployeeHealthCheck::where('id', $record->id)->update([
                    'skk_number' => $skkNumber,
                    'updated_by' => $this->karyawan,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);
                $record->skk_number = $skkNumber;
            }

            $payload = $this->decorate($record);

            return response()->json([
                'success' => true,
                'message' => 'Nomor SKK berhasil digenerate. Template PDF belum tersedia.',
                'data' => $payload,
                'skk_number' => $record->skk_number,
                'pdf_ready' => false,
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
                'ehc.tensi',
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

        return $this->baseQuery((int) date('Y'))
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

        return $item;
    }

    private function buildPayload(Request $request)
    {
        $tensi = (int) $request->tensi;

        return [
            'check_date' => $request->check_date ?: Carbon::now()->format('Y-m-d'),
            'check_time' => $this->normalizeTime($request->check_time ?: Carbon::now()->format('H:i')),
            'tensi' => $tensi,
            'tensi_classification' => EmployeeHealthCheck::classifyTensi($tensi),
            'saturasi' => $this->parseDecimal($request->saturasi),
            'nadi' => (int) $request->nadi,
            'suhu' => $this->parseDecimal($request->suhu),
            'stetoskop' => $this->normalizeStetoskop($request->stetoskop),
            'gula_darah' => $this->nullableDecimal($request->gula_darah),
            'asam_urat' => $this->nullableDecimal($request->asam_urat),
            'kolesterol' => $this->nullableDecimal($request->kolesterol),
            'keterangan' => $this->normalizeKeterangan($request->keterangan),
            'keluhan' => trim((string) ($request->keluhan ?: '')) ?: null,
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

        if (!$request->filled('tensi') && $request->tensi !== '0' && $request->tensi !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tensi wajib diisi',
            ], 422);
        }

        if ((int) $request->tensi <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tensi harus lebih dari 0',
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
        return (int) ($request->id ?: $request->health_check_id);
    }

    private function generateSkkNumber($record)
    {
        $datePart = Carbon::parse($record->check_date)->format('Ymd');

        return 'SKK-' . $datePart . '-' . str_pad((string) $record->id, 5, '0', STR_PAD_LEFT);
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
