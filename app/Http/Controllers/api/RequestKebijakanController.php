<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\RequestKebijakan;
use App\Services\GetBawahan;
use App\Services\KaryawanProfileService;
use App\Services\RenderKebijakanDocumentPdf;
use App\Services\RequestKebijakanNotificationService;
use App\Services\RequestKebijakanRevisionService;
use App\Services\RequestKebijakanVerifierService;
use App\Services\RequestKebijakanWorkflowService;
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestKebijakanController extends Controller
{
    private const STATUS_LABELS = RequestKebijakanWorkflowService::STATUS_LABELS;

    private const KATEGORI_LABELS = RequestKebijakanWorkflowService::KATEGORI_LABELS;

    private const TUJUAN_KEBIJAKAN_BARU_PREFIX = 'Memberikan ketentuan mengenai ';

    private const RUANG_LINGKUP_KEBIJAKAN_BARU_PREFIX = 'Ketetapan ini mengatur tentang ';

    private const ALLOWED_REQUESTER_GRADES = RequestKebijakanWorkflowService::APPROVER_GRADES;

    public function initialize(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        if ($employee) {
            $employee->loadMissing('jabatan');
        }

        $canRequest = $this->canRequestKebijakan($employee);

        return response()->json([
            'data' => [
                'employee' => $employee ? array_merge($employee->toArray(), [
                    'jabatan_label' => KaryawanProfileService::resolveJabatan($employee),
                ]) : null,
                'can_request' => $canRequest,
                'access_message' => $canRequest ? null : $this->getAccessDeniedMessage(),
                'counts' => $canRequest ? RequestKebijakanWorkflowService::getRequesterTabCounts($employee) : [],
            ],
            'message' => $canRequest
                ? 'Request kebijakan initialized successfully'
                : $this->getAccessDeniedMessage(),
        ], 200);
    }

    public function counts(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $this->ensureCanRequestKebijakan($employee);

        return response()->json([
            'data' => [
                'counts' => RequestKebijakanWorkflowService::getRequesterTabCounts($employee),
            ],
            'message' => 'Tab counts retrieved successfully',
        ], 200);
    }

    public function index(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $this->ensureCanRequestKebijakan($employee);

        $scope = $request->input('scope', 'pending');
        
        $query = RequestKebijakan::query()
            ->with(['requester.jabatan', 'requester.divisi', 'drafting'])
            ->orderByDesc('request_at');

        if ($scope === 'user_review') {
            $query = RequestKebijakanVerifierService::buildUserReviewScopeQuery($query, $employee);
        } else {
            $query = $this->applyEmployeeScope($query, $employee);
        }

        if ($scope === 'void') {
            $query = $this->applyVoidScope($query);
        } elseif ($scope === 'user_review') {
            // scoped above
        } elseif ($scope === 'completed') {
            $query = $this->applyCompletedScope($query);
        } else {
            $query = $this->applyPendingScope($query);
        }

        return DataTables::of($query)
            ->addColumn('display_status', fn ($row) => RequestKebijakanWorkflowService::resolveDisplayStatus($row))
            ->addColumn('display_kategori', fn ($row) => $this->resolveKategoriLabel($row->kategori))
            ->addColumn('can_delete', fn ($row) => $this->canDelete($row, $employee))
            ->addColumn('can_update', fn ($row) => $this->canUpdate($row, $employee))
            ->addColumn('can_user_review', fn ($row) => RequestKebijakanWorkflowService::canUserReviewRequest($row, $employee))
            ->addColumn('verification_progress', fn ($row) => RequestKebijakanVerifierService::getVerificationProgress($row))
            ->addColumn('forwarded_to_user_at', fn ($row) => $row->forwarded_to_user_at)
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

        $record->revision_meta = RequestKebijakanRevisionService::decodeRevisionMeta($record->revision_meta);

        return response()->json([
            'data' => [
                'request_kebijakan' => $record,
                'display_status' => RequestKebijakanWorkflowService::resolveDisplayStatus($record),
                'pipeline' => RequestKebijakanWorkflowService::buildPipeline($record),
                'can_delete' => $this->canDelete($record, $employee),
                'can_update' => $this->canUpdate($record, $employee),
            ],
            'message' => 'Detail request kebijakan berhasil diambil',
        ], 200);
    }

    public function activeDocuments(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $this->ensureCanRequestKebijakan($employee);

        $search = trim((string) $request->input('search', $request->input('keyword', '')));
        $limit = (int) $request->input('limit', 30);

        if ($search !== '' && mb_strlen($search) < 2) {
            return response()->json([
                'data' => [],
                'message' => 'Ketik minimal 2 karakter untuk mencari dokumen ketetapan.',
            ], 200);
        }

        return response()->json([
            'data' => RequestKebijakanRevisionService::listActiveDocuments(
                $search !== '' ? $search : null,
                $limit
            ),
            'message' => 'Daftar dokumen ketetapan aktif berhasil diambil',
        ], 200);
    }

    public function revisionBaseline(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $this->ensureCanRequestKebijakan($employee);

        $dokumenId = (int) $request->input('dokumen_id', $request->input('id', 0));
        $excludeRequestId = $request->input('exclude_request_id') ? (int) $request->input('exclude_request_id') : null;

        if ($dokumenId <= 0) {
            abort(422, 'ID dokumen ketetapan wajib diisi.');
        }

        return response()->json([
            'data' => RequestKebijakanRevisionService::getRevisionBaseline($dokumenId, $excludeRequestId),
            'message' => 'Baseline revisi berhasil diambil',
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
                'parent_kebijakan_dokumen_id' => $validated['parent_kebijakan_dokumen_id'] ?? null,
                'revision_meta' => isset($validated['revision_meta'])
                    ? json_encode($validated['revision_meta'])
                    : null,
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

            RequestKebijakanNotificationService::requestSubmitted($record);

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

    public function update(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $this->ensureCanRequestKebijakan($employee);

        $record = RequestKebijakan::findOrFail($request->id);

        $this->ensureCanAccess($record, $employee);

        if (!$this->canUpdate($record, $employee)) {
            return response()->json([
                'message' => 'Request hanya dapat diubah jika belum disetujui',
            ], 422);
        }

        $validated = $this->validatePayload($request, (int) $record->id);

        DB::beginTransaction();
        try {
            $record->update([
                'kategori' => $validated['kategori'],
                'parent_kebijakan_dokumen_id' => $validated['parent_kebijakan_dokumen_id'] ?? null,
                'revision_meta' => isset($validated['revision_meta'])
                    ? json_encode($validated['revision_meta'])
                    : null,
                'judul' => $validated['judul'],
                'tujuan' => $validated['tujuan'],
                'ruang_lingkup' => $validated['ruang_lingkup'],
                'definisi' => $validated['definisi'],
                'isi_ketetapan' => $validated['isi_ketetapan'],
                'catatan' => $validated['catatan'],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Request kebijakan berhasil diperbarui',
                'data' => [
                    'id' => $record->id,
                    'no_request' => $record->no_request,
                ],
            ], 200);
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

    public function previewPdf(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $this->ensureCanRequestKebijakan($employee);

        $record = RequestKebijakan::with('drafting')->findOrFail($request->id);
        $this->ensureCanAccess($record, $employee);

        if (
            !$record->drafting
            || $record->drafting->status !== 'submitted'
            || !in_array($record->status, ['completed', 'pending_user_review'], true)
        ) {
            return response()->json(['message' => 'Draft kebijakan belum tersedia untuk review'], 404);
        }

        $pdfString = app(RenderKebijakanDocumentPdf::class)->renderFromDraft($record->drafting);

        return response()->json([
            'data' => base64_encode($pdfString),
            'message' => 'PDF berhasil dibuat',
        ], 200);
    }

    public function processUserReview(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $this->ensureCanRequestKebijakan($employee);

        $action = $request->input('action');
        $record = RequestKebijakan::with('drafting')->findOrFail($request->input('data.parent_id'));

        $this->ensureCanAccess($record, $employee);

        if (!RequestKebijakanWorkflowService::canUserReviewRequest($record, $employee)) {
            return response()->json(['message' => 'Request tidak dapat diverifikasi pada tahap ini'], 422);
        }

        DB::beginTransaction();
        try {
            if (in_array($action, ['verify_draft', 'approve_user_review'], true)) {
                $verificationDate = $request->input('data.verification_date');

                if (RequestKebijakanVerifierService::hasVerifiers($record)) {
                    RequestKebijakanVerifierService::verify($record, $employee, $verificationDate);
                } else {
                    $record->update([
                        'status' => 'pending_legal_final',
                        'user_reviewed_by' => $employee->nama_lengkap,
                        'user_reviewed_at' => Carbon::now(),
                    ]);

                    RequestKebijakanNotificationService::allVerifiersCompletedPendingLegalFinal($record->fresh());
                }

                DB::commit();

                return response()->json([
                    'message' => 'Draft kebijakan berhasil diverifikasi',
                ], 200);
            }

            if (in_array($action, ['reject_verifier_review', 'reject_user_review'], true)) {
                $reason = trim((string) ($request->input('data.reason') ?? ''));

                if ($reason === '' || trim(strip_tags($reason)) === '') {
                    return response()->json(['message' => 'Alasan penolakan verifikasi wajib diisi'], 422);
                }

                if (RequestKebijakanVerifierService::hasVerifiers($record)) {
                    RequestKebijakanVerifierService::reject($record, $employee, $reason);
                } else {
                    $record->update([
                        'status' => 'pending_user_reject_review',
                        'user_review_rejected_by' => $employee->nama_lengkap,
                        'user_review_rejected_at' => Carbon::now(),
                        'user_review_rejected_note' => $reason,
                    ]);

                    RequestKebijakanNotificationService::userReviewRejectedPendingApproval($record->fresh(), $employee);
                }

                DB::commit();

                return response()->json([
                    'message' => 'Penolakan verifikasi berhasil dicatat',
                ], 200);
            }

            DB::rollBack();

            return response()->json(['message' => 'Aksi tidak valid'], 422);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    private function validatePayload(Request $request, ?int $excludeRequestId = null): array
    {
        $kategori = strtolower(trim((string) $request->input('kategori', 'new')));

        if (!array_key_exists($kategori, self::KATEGORI_LABELS)) {
            abort(422, 'Kategori request tidak valid.');
        }

        if ($kategori === 'revision') {
            return RequestKebijakanRevisionService::validateRevisionPayload($request, $excludeRequestId);
        }

        if ($kategori === 'termination') {
            abort(422, 'Fitur terminasi ketetapan sedang dalam pengembangan.');
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

        if ($this->isOnlyDefaultPrefix($tujuan, self::TUJUAN_KEBIJAKAN_BARU_PREFIX)) {
            abort(422, 'Tujuan wajib diisi');
        }

        if ($this->isOnlyDefaultPrefix($ruangLingkup, self::RUANG_LINGKUP_KEBIJAKAN_BARU_PREFIX)) {
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
            'parent_kebijakan_dokumen_id' => null,
            'revision_meta' => null,
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

    private function isOnlyDefaultPrefix(?string $value, string $prefix): bool
    {
        if ($this->isEmptyHtml($value)) {
            return true;
        }

        $text = trim(strip_tags(html_entity_decode($value)));

        return $text === trim($prefix);
    }

    private function applyPendingScope($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereIn('status', [
                    'waiting_approval',
                    'approved',
                    'on_process',
                    'pending_user_review',
                    'pending_user_reject_review',
                    'pending_legal_final',
                    'pending_director_approval',
                ])
                    ->orWhere(function ($sub) {
                        $sub->where('status', 'completed')
                            ->whereNull('forwarded_to_user_at')
                            ->whereNull('user_reviewed_at')
                            ->whereHas('drafting', function ($draft) {
                                $draft->where('is_active', true)->where('status', 'submitted');
                            });
                    });
            });
    }


    private function applyCompletedScope($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'completed')
            ->whereHas('activeKebijakanDokumen');
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
        if (RequestKebijakanVerifierService::isAssignedVerifier($record, $employee)) {
            return;
        }

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
        return $this->canUpdate($record, $employee);
    }

    private function canUpdate(RequestKebijakan $record, $employee): bool
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
        do {
            $noRequest = str_replace('.', '/', (string) microtime(true));
        } while (RequestKebijakan::where('no_request', $noRequest)->exists());

        return $noRequest;
    }
}
