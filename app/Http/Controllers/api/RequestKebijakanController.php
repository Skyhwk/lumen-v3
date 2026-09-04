<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\RequestKebijakan;
use App\Services\GetBawahan;
use App\Services\RequestKebijakanWorkflowService;
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestKebijakanController extends Controller
{
    private const ROMAN_MONTHS = [
        '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV',
        '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII',
        '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII',
    ];

    private const STATUS_LABELS = RequestKebijakanWorkflowService::STATUS_LABELS;

    private const KATEGORI_LABELS = RequestKebijakanWorkflowService::KATEGORI_LABELS;

    private const ALLOWED_REQUESTER_GRADES = RequestKebijakanWorkflowService::APPROVER_GRADES;

    public function initialize(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $canRequest = $this->canRequestKebijakan($employee);

        return response()->json([
            'data' => [
                'employee' => $employee,
                'can_request' => $canRequest,
                'access_message' => $canRequest ? null : $this->getAccessDeniedMessage(),
            ],
            'message' => $canRequest
                ? 'Request kebijakan initialized successfully'
                : $this->getAccessDeniedMessage(),
        ], 200);
    }

    public function index(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $this->ensureCanRequestKebijakan($employee);

        $scope = $request->input('scope', 'pending');
        
        $query = RequestKebijakan::query()
            ->with(['requester.jabatan', 'requester.divisi'])
            ->orderByDesc('request_at');

        $query = $this->applyEmployeeScope($query, $employee);

        if ($scope === 'void') {
            $query = $this->applyVoidScope($query);
        } elseif ($scope === 'completed') {
            $query = $this->applyCompletedScope($query);
        } else {
            $query = $this->applyPendingScope($query);
        }

        return DataTables::of($query)
            ->addColumn('display_status', fn ($row) => $this->resolveDisplayStatus($row))
            ->addColumn('display_kategori', fn ($row) => $this->resolveKategoriLabel($row->kategori))
            ->addColumn('can_delete', fn ($row) => $this->canDelete($row, $employee))
            ->addColumn('void_reason', fn ($row) => $this->resolveVoidReason($row))
            ->filterColumn('no_request', fn ($q, $keyword) => $q->where('no_request', 'like', "%{$keyword}%"))
            ->filterColumn('display_kategori', function ($q, $keyword) {
                $q->where(function ($sub) use ($keyword) {
                    foreach (self::KATEGORI_LABELS as $key => $label) {
                        if (stripos($label, $keyword) !== false) {
                            $sub->orWhere('kategori', $key);
                        }
                    }
                    $sub->orWhere('kategori', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('judul', fn ($q, $keyword) => $q->where('judul', 'like', "%{$keyword}%"))
            ->filterColumn('display_status', function ($q, $keyword) {
                $q->where(function ($sub) use ($keyword) {
                    foreach (self::STATUS_LABELS as $status => $label) {
                        if (stripos($label, $keyword) !== false) {
                            $sub->orWhere('status', $status);
                        }
                    }
                    $sub->orWhere('status', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('request_by', fn ($q, $keyword) => $q->where('request_by', 'like', "%{$keyword}%"))
            ->make(true);
    }

    public function show(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $this->ensureCanRequestKebijakan($employee);

        $record = RequestKebijakan::with(['requester.jabatan', 'requester.divisi'])
            ->findOrFail($request->id);

        $this->ensureCanAccess($record, $employee);

        return response()->json([
            'data' => [
                'request_kebijakan' => $record,
                'display_status' => $this->resolveDisplayStatus($record),
                'pipeline' => RequestKebijakanWorkflowService::buildPipeline($record),
                'can_delete' => $this->canDelete($record, $employee),
            ],
            'message' => 'Detail request kebijakan berhasil diambil',
        ], 200);
    }

    public function save(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $this->ensureCanRequestKebijakan($employee);

        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $record = RequestKebijakan::create([
                'no_request' => $this->generateNoRequest(),
                'kategori' => $validated['kategori'],
                'judul' => $validated['judul'],
                'tujuan' => $validated['tujuan'],
                'ruang_lingkup' => $validated['ruang_lingkup'],
                'definisi' => $validated['definisi'],
                'isi_ketetapan' => $validated['isi_ketetapan'],
                'catatan' => $validated['catatan'],
                'status' => 'waiting_approval',
                'request_by' => $employee->nama_lengkap,
                'request_at' => Carbon::now(),
                'is_active' => true,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Request kebijakan berhasil dibuat',
                'data' => [
                    'id' => $record->id,
                    'no_request' => $record->no_request,
                ],
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $this->ensureCanRequestKebijakan($employee);

        $record = RequestKebijakan::findOrFail($request->id);

        $this->ensureCanAccess($record, $employee);

        if (!$this->canDelete($record, $employee)) {
            return response()->json([
                'message' => 'Request hanya dapat dihapus jika belum disetujui',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $record->update([
                'is_active' => false,
                'deleted_by' => $employee->nama_lengkap,
                'deleted_at' => Carbon::now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Request kebijakan berhasil dihapus',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    private function validatePayload(Request $request): array
    {
        $kategori = strtolower(trim((string) $request->input('kategori', 'new')));

        if (!array_key_exists($kategori, self::KATEGORI_LABELS)) {
            abort(422, 'Kategori request tidak valid.');
        }

        if ($kategori !== 'new') {
            abort(422, 'Fitur kategori ini sedang dalam pengembangan. Saat ini hanya Kebijakan Baru yang dapat diajukan.');
        }

        $judul = trim((string) $request->input('judul', ''));
        $tujuan = (string) $request->input('tujuan', '');
        $ruangLingkup = (string) $request->input('ruang_lingkup', '');
        $definisi = (string) $request->input('definisi', '');
        $isiKetetapan = (string) $request->input('isi_ketetapan', '');
        $catatan = (string) $request->input('catatan', '');

        if ($judul === '') {
            abort(422, 'Judul wajib diisi');
        }

        if ($this->isEmptyHtml($tujuan)) {
            abort(422, 'Tujuan wajib diisi');
        }

        if ($this->isEmptyHtml($ruangLingkup)) {
            abort(422, 'Ruang lingkup wajib diisi');
        }

        if ($this->isEmptyHtml($definisi)) {
            abort(422, 'Definisi wajib diisi');
        }

        if ($this->isEmptyHtml($isiKetetapan)) {
            abort(422, 'Isi ketetapan wajib diisi');
        }

        return [
            'kategori' => $kategori,
            'judul' => $judul,
            'tujuan' => $tujuan,
            'ruang_lingkup' => $ruangLingkup,
            'definisi' => $definisi,
            'isi_ketetapan' => $isiKetetapan,
            'catatan' => $this->isEmptyHtml($catatan) ? null : $catatan,
        ];
    }

    private function isEmptyHtml(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return true;
        }

        $text = trim(strip_tags(html_entity_decode($value)));

        return $text === '';
    }

    private function applyPendingScope($query)
    {
        return $query
            ->where('is_active', true)
            ->whereIn('status', ['waiting_approval', 'approved', 'on_process']);
    }

    private function applyCompletedScope($query)
    {
        return $query
            ->where('is_active', true)
            ->where('status', 'completed');
    }

    private function applyVoidScope($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'rejected')
                ->orWhere(function ($sub) {
                    $sub->where('is_active', false)
                        ->whereNotNull('deleted_at');
                });
        })->orderByRaw('COALESCE(rejected_at, deleted_at, request_at) DESC');
    }

    private function normalizeGrade(?string $grade): string
    {
        return RequestKebijakanWorkflowService::normalizeGrade($grade);
    }

    private function canRequestKebijakan($employee): bool
    {
        return RequestKebijakanWorkflowService::canApprove($employee);
    }

    private function getAccessDeniedMessage(): string
    {
        return 'Maaf, Anda tidak memiliki otorisasi untuk mengakses modul Request Kebijakan. '
            . 'Fitur ini hanya tersedia bagi karyawan dengan grade Manager, Senior Manager, Executive, dan Director. '
            . 'Apabila Anda memerlukan bantuan terkait kebijakan perusahaan, silakan hubungi atasan atau tim HRD.';
    }

    private function ensureCanRequestKebijakan($employee): void
    {
        if (!$this->canRequestKebijakan($employee)) {
            abort(403, $this->getAccessDeniedMessage());
        }
    }

    private function applyEmployeeScope($query, $employee)
    {
        $grade = $this->normalizeGrade($employee->grade ?? '');

        if (in_array($grade, ['EXECUTIVE', 'DIRECTOR'], true)) {
            return $query;
        }

        if (in_array($grade, ['MANAGER', 'SENIOR MANAGER'], true)) {
            $creators = GetBawahan::where('id', $employee->id)->get()->pluck('nama_lengkap')->toArray();
            $creators[] = $employee->nama_lengkap;

            return $query->whereIn('request_by', $creators);
        }

        return $query->whereRaw('1 = 0');
    }

    private function ensureCanAccess(RequestKebijakan $record, $employee): void
    {
        $grade = $this->normalizeGrade($employee->grade ?? '');

        if (in_array($grade, ['EXECUTIVE', 'DIRECTOR'], true)) {
            return;
        }

        if (in_array($grade, ['MANAGER', 'SENIOR MANAGER'], true)) {
            $creators = GetBawahan::where('id', $employee->id)->get()->pluck('nama_lengkap')->toArray();
            $creators[] = $employee->nama_lengkap;

            if (!in_array($record->request_by, $creators, true)) {
                abort(403, 'Anda tidak memiliki akses ke request ini');
            }

            return;
        }

        abort(403, $this->getAccessDeniedMessage());
    }

    private function canDelete(RequestKebijakan $record, $employee): bool
    {
        return $record->is_active
            && $record->status === 'waiting_approval'
            && $record->request_by === $employee->nama_lengkap;
    }

    private function resolveDisplayStatus(RequestKebijakan $record): string
    {
        if (!$record->is_active && $record->deleted_at) {
            return 'Void - Pemohon';
        }

        return self::STATUS_LABELS[$record->status] ?? ucfirst(str_replace('_', ' ', (string) $record->status));
    }

    private function resolveKategoriLabel(?string $kategori): string
    {
        return RequestKebijakanWorkflowService::resolveKategoriLabel($kategori);
    }

    private function resolveVoidReason(RequestKebijakan $record): ?string
    {
        if ($record->status === 'rejected') {
            return $record->rejected_note ?: 'Ditolak';
        }

        if (!$record->is_active && $record->deleted_at) {
            return 'Dihapus oleh pemohon';
        }

        return null;
    }

    private function generateNoRequest(): string
    {
        $year = date('y');
        $month = self::ROMAN_MONTHS[date('m')];
        $prefix = "ISL/RK/{$year}-{$month}/";

        $latest = RequestKebijakan::where('no_request', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(no_request, "/", -1) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = 1;
        if ($latest) {
            $lastPart = substr($latest->no_request, strrpos($latest->no_request, '/') + 1);
            $nextNumber = (int) $lastPart + 1;
        }

        $padLength = max(4, strlen((string) $nextNumber));

        return $prefix . str_pad($nextNumber, $padLength, '0', STR_PAD_LEFT);
    }
}
