<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\RequestKebijakan;
use App\Services\RenderKebijakanDocumentPdf;
use App\Services\KebijakanDokumenService;
use App\Services\RequestKebijakanNotificationService;
use App\Services\RequestKebijakanRevisionService;
use App\Services\RequestKebijakanVerifierService;
use App\Services\RequestKebijakanWorkflowService;
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestKebijakanApprovalController extends Controller
{
    public function initialize(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        $canApprove = RequestKebijakanWorkflowService::canApprove($employee);
        $canLegalFinal = RequestKebijakanNotificationService::canAccessApprovalMenu($employee);
        $canAccess = $canApprove || $canLegalFinal;

        return response()->json([
            'data' => [
                'employee' => $employee,
                'can_approve' => $canApprove,
                'can_legal_final' => $canLegalFinal,
                'counts' => $canAccess ? RequestKebijakanWorkflowService::getApprovalTabCounts() : [],
            ],
            'message' => 'Request kebijakan approval initialized successfully',
        ], 200);
    }

    public function counts(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        if (
            !RequestKebijakanWorkflowService::canApprove($employee)
            && !RequestKebijakanNotificationService::canAccessApprovalMenu($employee)
        ) {
            abort(403, $this->getAccessDeniedMessage());
        }

        return response()->json([
            'data' => [
                'counts' => RequestKebijakanWorkflowService::getApprovalTabCounts(),
            ],
            'message' => 'Tab counts retrieved successfully',
        ], 200);
    }

    public function index(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        $canApprove = RequestKebijakanWorkflowService::canApprove($employee);
        $canLegalFinal = RequestKebijakanNotificationService::canAccessApprovalMenu($employee);

        if (!$canApprove && !$canLegalFinal) {
            abort(403, $this->getAccessDeniedMessage());
        }

        $scope = $request->input('scope', 'pending');

        $query = RequestKebijakan::query()
            ->from('request_kebijakan')
            ->with(['requester.jabatan', 'requester.divisi', 'drafting'])
            ->where('request_kebijakan.is_active', true);

        if ($scope === 'approved_waiting') {
            $query->where('request_kebijakan.status', 'approved')
                ->orderByDesc('request_kebijakan.approval_at');
        } elseif ($scope === 'review') {
            $query = RequestKebijakanWorkflowService::buildExecutiveReviewQuery()
                ->with(['requester.jabatan', 'requester.divisi', 'drafting']);
        } elseif ($scope === 'waiting_user_review') {
            $query->where('request_kebijakan.status', 'pending_user_review')
                ->orderByDesc('request_kebijakan.forwarded_to_user_at');
        } elseif ($scope === 'user_reject_review') {
            $query->where('request_kebijakan.status', 'pending_user_reject_review')
                ->orderByDesc('request_kebijakan.user_review_rejected_at');
        } elseif ($scope === 'legal_final') {
            $query->where('request_kebijakan.status', 'pending_legal_final')
                ->orderByDesc('request_kebijakan.user_reviewed_at');
        } else {
            $query->where('request_kebijakan.status', 'waiting_approval')
                ->orderByDesc('request_kebijakan.request_at');
        }

        return DataTables::of($query)
            ->addColumn('display_status', fn ($row) => RequestKebijakanWorkflowService::resolveDisplayStatus($row))
            ->addColumn('display_kategori', fn ($row) => RequestKebijakanWorkflowService::resolveKategoriLabel($row->kategori))
            ->addColumn('can_approve', fn () => RequestKebijakanWorkflowService::canApprove($employee))
            ->addColumn('submitted_by', fn ($row) => optional($row->drafting)->submitted_by)
            ->addColumn('submitted_at', fn ($row) => optional($row->drafting)->submitted_at)
            ->addColumn('forwarded_to_user_by', fn ($row) => $row->forwarded_to_user_by)
            ->addColumn('forwarded_to_user_at', fn ($row) => $row->forwarded_to_user_at)
            ->addColumn('user_review_rejected_by', fn ($row) => $row->user_review_rejected_by)
            ->addColumn('user_review_rejected_at', fn ($row) => $row->user_review_rejected_at)
            ->addColumn('user_review_rejected_note', fn ($row) => $row->user_review_rejected_note)
            ->addColumn('verification_progress', fn ($row) => RequestKebijakanVerifierService::getVerificationProgress($row))
            ->filterColumn('no_request', fn ($q, $keyword) => $q->where('request_kebijakan.no_request', 'like', "%{$keyword}%"))
            ->filterColumn('judul', fn ($q, $keyword) => $q->where('request_kebijakan.judul', 'like', "%{$keyword}%"))
            ->filterColumn('display_kategori', function ($q, $keyword) {
                $q->where(function ($sub) use ($keyword) {
                    foreach (RequestKebijakanWorkflowService::KATEGORI_LABELS as $key => $label) {
                        if (stripos($label, $keyword) !== false) {
                            $sub->orWhere('request_kebijakan.kategori', $key);
                        }
                    }
                    $sub->orWhere('request_kebijakan.kategori', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('request_by', fn ($q, $keyword) => $q->where('request_kebijakan.request_by', 'like', "%{$keyword}%"))
            ->make(true);
    }

    public function show(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        if (!RequestKebijakanWorkflowService::canApprove($employee)) {
            abort(403, $this->getAccessDeniedMessage());
        }

        $record = RequestKebijakan::with(['requester.jabatan', 'requester.divisi', 'drafting'])
            ->findOrFail($request->id);

        $record->revision_meta = RequestKebijakanRevisionService::decodeRevisionMeta($record->revision_meta);

        return response()->json([
            'data' => [
                'request_kebijakan' => $record,
                'display_status' => RequestKebijakanWorkflowService::resolveDisplayStatus($record),
                'display_kategori' => RequestKebijakanWorkflowService::resolveKategoriLabel($record->kategori),
                'pipeline' => RequestKebijakanWorkflowService::buildPipeline($record),
            ],
            'message' => 'Detail request kebijakan berhasil diambil',
        ], 200);
    }

    public function verifierCandidates(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        if (!RequestKebijakanWorkflowService::canApprove($employee)) {
            abort(403, $this->getAccessDeniedMessage());
        }

        return response()->json([
            'data' => RequestKebijakanVerifierService::getVerifierCandidates(),
            'message' => 'Daftar reviewer berhasil diambil',
        ], 200);
    }

    public function previewPdf(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        if (!RequestKebijakanWorkflowService::canApprove($employee)) {
            abort(403, $this->getAccessDeniedMessage());
        }

        $record = RequestKebijakan::with('drafting')->findOrFail($request->id);

        if (
            !$record->drafting
            || $record->drafting->status !== 'submitted'
            || !in_array($record->status, ['completed', 'pending_user_review', 'pending_user_reject_review', 'pending_legal_final', 'pending_director_approval'], true)
        ) {
            return response()->json(['message' => 'Draft kebijakan belum tersedia untuk review'], 404);
        }

        $pdfString = app(RenderKebijakanDocumentPdf::class)->renderForRequest($record);

        return response()->json([
            'data' => base64_encode($pdfString),
            'message' => 'PDF berhasil dibuat',
        ], 200);
    }

    public function process(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        $action = $request->input('action');
        $parentId = $request->input('data.parent_id');

        if ($action === 'legal_final_verify') {
            return $this->processLegalFinalVerify($request, $employee, $parentId);
        }

        if (!RequestKebijakanWorkflowService::canApprove($employee)) {
            abort(403, $this->getAccessDeniedMessage());
        }

        if (in_array($action, ['forward_to_verifiers', 'forward_to_user', 'reject_review', 'reject_user_reject_to_legal'], true)) {
            return $this->processReview($request, $employee, $action, $parentId);
        }

        $record = RequestKebijakan::findOrFail($parentId);

        if ($record->status !== 'waiting_approval' || !$record->is_active) {
            return response()->json(['message' => 'Request tidak dapat diproses pada tahap ini'], 422);
        }

        DB::beginTransaction();
        try {
            if ($action === 'approve') {
                $record->update([
                    'status' => 'approved',
                    'approval_by' => $employee->nama_lengkap,
                    'approval_at' => Carbon::now(),
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejected_note' => null,
                ]);

                DB::commit();

                RequestKebijakanNotificationService::requestApproved($record->fresh(), $employee);

                return response()->json([
                    'message' => 'Request kebijakan berhasil disetujui dan diteruskan ke tim legal untuk drafting',
                ], 200);
            }

            if ($action === 'reject') {
                $reason = trim((string) ($request->input('data.reason') ?? ''));

                if ($reason === '' || trim(strip_tags($reason)) === '') {
                    return response()->json(['message' => 'Alasan penolakan wajib diisi'], 422);
                }

                $record->update([
                    'status' => 'rejected',
                    'rejected_by' => $employee->nama_lengkap,
                    'rejected_at' => Carbon::now(),
                    'rejected_note' => $reason,
                ]);

                DB::commit();

                RequestKebijakanNotificationService::requestRejected($record->fresh(), $employee);

                return response()->json([
                    'message' => 'Request kebijakan berhasil ditolak',
                ], 200);
            }

            DB::rollBack();

            return response()->json(['message' => 'Aksi tidak valid'], 422);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    private function processReview(Request $request, $employee, string $action, $parentId)
    {
        $record = RequestKebijakan::with('drafting')->findOrFail($parentId);

        if ($action === 'reject_user_reject_to_legal') {
            return $this->processUserRejectToLegal($request, $employee, $record);
        }

        if (!RequestKebijakanWorkflowService::canExecutiveReviewSubmittedDraft($record)) {
            return response()->json(['message' => 'Draft kebijakan tidak dapat direview pada tahap ini'], 422);
        }

        DB::beginTransaction();
        try {
            if (in_array($action, ['forward_to_verifiers', 'forward_to_user'], true)) {
                $verifierIds = $request->input('data.verifier_ids', []);

                if (!is_array($verifierIds) || empty($verifierIds)) {
                    DB::rollBack();

                    return response()->json(['message' => 'Minimal 1 reviewer wajib dipilih'], 422);
                }

                RequestKebijakanVerifierService::assignVerifiers($record, $verifierIds, $employee);

                DB::commit();

                return response()->json([
                    'message' => 'Draft kebijakan berhasil dikirim untuk verifikasi reviewer terpilih',
                ], 200);
            }

            if ($action === 'reject_review') {
                $reason = trim((string) ($request->input('data.reason') ?? ''));

                if ($reason === '' || trim(strip_tags($reason)) === '') {
                    return response()->json(['message' => 'Alasan penolakan review wajib diisi'], 422);
                }

                $record->drafting->update([
                    'status' => 'in_progress',
                    'review_rejected_by' => $employee->nama_lengkap,
                    'review_rejected_at' => Carbon::now(),
                    'review_rejected_note' => $reason,
                    'review_rejected_source' => 'executive',
                    'review_reject_used_user_note' => false,
                    'submitted_by' => null,
                    'submitted_at' => null,
                    'updated_by' => $employee->nama_lengkap,
                    'updated_at' => Carbon::now(),
                ]);

                $record->update([
                    'status' => 'on_process',
                    'forwarded_to_user_by' => null,
                    'forwarded_to_user_at' => null,
                ]);

                DB::commit();

                RequestKebijakanNotificationService::reviewRejectedByExecutive($record->fresh(), $employee);

                return response()->json([
                    'message' => 'Draft kebijakan dikembalikan ke tim legal untuk perbaikan',
                ], 200);
            }

            DB::rollBack();

            return response()->json(['message' => 'Aksi tidak valid'], 422);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    private function processUserRejectToLegal(Request $request, $employee, RequestKebijakan $record)
    {
        if (
            $record->status !== 'pending_user_reject_review'
            || !$record->is_active
            || !$record->drafting
            || $record->drafting->status !== 'submitted'
        ) {
            return response()->json(['message' => 'Penolakan user tidak dapat diproses pada tahap ini'], 422);
        }

        $useUserNote = filter_var($request->input('data.use_user_reject_reason'), FILTER_VALIDATE_BOOLEAN);
        $reason = trim((string) ($request->input('data.reason') ?? ''));

        if ($useUserNote) {
            $reason = trim((string) ($record->user_review_rejected_note ?? ''));
        }

        if ($reason === '' || trim(strip_tags($reason)) === '') {
            return response()->json(['message' => 'Alasan penolakan wajib diisi'], 422);
        }

        DB::beginTransaction();
        try {
            $record->drafting->update([
                'status' => 'in_progress',
                'review_rejected_by' => $employee->nama_lengkap,
                'review_rejected_at' => Carbon::now(),
                'review_rejected_note' => $reason,
                'review_rejected_source' => 'executive_after_user',
                'review_reject_used_user_note' => $useUserNote,
                'submitted_by' => null,
                'submitted_at' => null,
                'updated_by' => $employee->nama_lengkap,
                'updated_at' => Carbon::now(),
            ]);

            $record->update([
                'status' => 'on_process',
                'forwarded_to_user_by' => null,
                'forwarded_to_user_at' => null,
                'user_reviewed_by' => null,
                'user_reviewed_at' => null,
            ]);

            DB::commit();

            RequestKebijakanNotificationService::reviewRejectedAfterUserToLegal($record->fresh(), $employee, $useUserNote);

            return response()->json([
                'message' => 'Draft kebijakan dikembalikan ke tim legal untuk perbaikan',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    private function processLegalFinalVerify(Request $request, $employee, $parentId)
    {
        $record = RequestKebijakan::with('drafting')->findOrFail($parentId);

        DB::beginTransaction();
        try {
            KebijakanDokumenService::submitLegalFinalVerification(
                $record,
                $employee,
                [
                    'terbitan' => $request->input('data.terbitan', $request->input('data.cetakan', 1)),
                    'revisian' => $request->input('data.revisian', 0),
                    'tanggal_terbitan' => $request->input('data.tanggal_terbitan', $request->input('data.tanggal_pengesahan')),
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Verifikasi final legal berhasil. Dokumen menunggu pengesahan Director.',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    private function getAccessDeniedMessage(): string
    {
        return 'Maaf, Anda tidak memiliki otorisasi untuk melakukan approval request kebijakan. '
            . 'Fitur ini hanya tersedia bagi karyawan dengan grade Manager, Senior Manager, dan Director.';
    }
}
