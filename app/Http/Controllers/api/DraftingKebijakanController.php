<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DraftingKebijakan;
use App\Models\RequestKebijakan;
use App\Services\RequestKebijakanNotificationService;
use App\Services\RequestKebijakanVerifierService;
use App\Services\RequestKebijakanWorkflowService;
use App\Services\RenderKebijakanDocumentPdf;
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DraftingKebijakanController extends Controller
{
    public function initialize(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        return response()->json([
            'data' => [
                'employee' => $employee,
            ],
            'message' => 'Drafting kebijakan initialized successfully',
        ], 200);
    }

    public function index(Request $request)
    {
        $scope = $request->input('scope', 'waiting');

        $query = RequestKebijakan::query()
            ->with(['requester.jabatan', 'requester.divisi', 'drafting'])
            ->where('is_active', true)
            ->orderByDesc('approval_at');

        if ($scope === 'in_progress') {
            $query->where('status', 'on_process')
                ->whereHas('drafting', function ($q) {
                    $q->where('is_active', true)->where('status', 'in_progress');
                });
        } else {
            $query->where('status', 'approved')
                ->whereDoesntHave('drafting', function ($q) {
                    $q->where('is_active', true);
                });
        }

        return DataTables::of($query)
            ->addColumn('display_status', fn ($row) => RequestKebijakanWorkflowService::resolveDisplayStatus($row))
            ->addColumn('display_kategori', fn ($row) => RequestKebijakanWorkflowService::resolveKategoriLabel($row->kategori))
            ->addColumn('draft_status', fn ($row) => optional($row->drafting)->status)
            ->addColumn('has_draft', fn ($row) => !!$row->drafting)
            ->addColumn('has_review_rejection', fn ($row) => !empty(optional($row->drafting)->review_rejected_note))
            ->addColumn('review_rejected_by', fn ($row) => optional($row->drafting)->review_rejected_by)
            ->addColumn('review_rejected_at', fn ($row) => optional($row->drafting)->review_rejected_at)
            ->addColumn('review_rejected_note', fn ($row) => optional($row->drafting)->review_rejected_note)
            ->addColumn('review_rejected_source', fn ($row) => optional($row->drafting)->review_rejected_source)
            ->addColumn('review_reject_used_user_note', fn ($row) => (bool) optional($row->drafting)->review_reject_used_user_note)
            ->addColumn('review_reject_source_label', fn ($row) => RequestKebijakanWorkflowService::resolveReviewRejectSourceLabel(optional($row->drafting)->review_rejected_source))
            ->addColumn('user_review_rejected_by', fn ($row) => $row->user_review_rejected_by)
            ->addColumn('user_review_rejected_at', fn ($row) => $row->user_review_rejected_at)
            ->addColumn('user_review_rejected_note', fn ($row) => $row->user_review_rejected_note)
            ->filterColumn('no_request', fn ($q, $keyword) => $q->where('no_request', 'like', "%{$keyword}%"))
            ->filterColumn('judul', fn ($q, $keyword) => $q->where('judul', 'like', "%{$keyword}%"))
            ->filterColumn('display_kategori', function ($q, $keyword) {
                $q->where(function ($sub) use ($keyword) {
                    foreach (RequestKebijakanWorkflowService::KATEGORI_LABELS as $key => $label) {
                        if (stripos($label, $keyword) !== false) {
                            $sub->orWhere('kategori', $key);
                        }
                    }
                    $sub->orWhere('kategori', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('request_by', fn ($q, $keyword) => $q->where('request_by', 'like', "%{$keyword}%"))
            ->make(true);
    }

    public function show(Request $request)
    {
        $record = RequestKebijakan::with(['requester.jabatan', 'requester.divisi', 'drafting'])
            ->findOrFail($request->id);

        return response()->json([
            'data' => [
                'request_kebijakan' => $record,
                'drafting' => $record->drafting,
                'display_status' => RequestKebijakanWorkflowService::resolveDisplayStatus($record),
                'display_kategori' => RequestKebijakanWorkflowService::resolveKategoriLabel($record->kategori),
                'pipeline' => RequestKebijakanWorkflowService::buildPipeline($record),
            ],
            'message' => 'Detail drafting kebijakan berhasil diambil',
        ], 200);
    }

    public function showDraft(Request $request)
    {
        $record = RequestKebijakan::with('drafting')->findOrFail($request->id);

        if (!$record->drafting) {
            return response()->json(['message' => 'Draft kebijakan belum tersedia'], 404);
        }

        return response()->json([
            'data' => [
                'request_kebijakan' => $record,
                'drafting' => $record->drafting,
            ],
            'message' => 'Draft kebijakan berhasil diambil',
        ], 200);
    }

    public function process(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $record = RequestKebijakan::with('drafting')->findOrFail($request->id);

        if ($record->status !== 'approved' || !$record->is_active) {
            return response()->json(['message' => 'Request tidak dapat diproses pada tahap ini'], 422);
        }

        if ($record->drafting) {
            return response()->json(['message' => 'Draft kebijakan untuk request ini sudah diproses'], 422);
        }

        DB::beginTransaction();
        try {
            DraftingKebijakan::create([
                'request_kebijakan_id' => $record->id,
                'judul' => $record->judul,
                'tujuan' => $record->tujuan,
                'ruang_lingkup' => $record->ruang_lingkup,
                'definisi' => $record->definisi,
                'isi_ketetapan' => $record->isi_ketetapan,
                'catatan_legal' => $record->catatan,
                'status' => 'in_progress',
                'processed_by' => $employee->nama_lengkap,
                'processed_at' => Carbon::now(),
            ]);

            $record->update([
                'status' => 'on_process',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Request kebijakan berhasil masuk ke tahap drafting',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function saveDraft(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $record = RequestKebijakan::with('drafting')->findOrFail($request->id);

        $judul = trim((string) $request->input('judul', ''));
        $divisiBagian = trim((string) $request->input('divisi_bagian', ''));

        if ($divisiBagian === '') {
            return response()->json(['message' => 'Divisi / bagian wajib diisi'], 422);
        }

        if ($judul === '') {
            return response()->json(['message' => 'Judul draft wajib diisi'], 422);
        }

        $draftPayload = [
            'divisi_bagian' => $divisiBagian,
            'judul' => $judul,
            'tujuan' => (string) $request->input('tujuan', ''),
            'ruang_lingkup' => (string) $request->input('ruang_lingkup', ''),
            'definisi' => (string) $request->input('definisi', ''),
            'isi_ketetapan' => (string) $request->input('isi_ketetapan', ''),
            'catatan_legal' => (string) $request->input('catatan_legal', '') ?: null,
        ];

        if ($record->status === 'approved' && !$record->drafting && $record->is_active) {
            DB::beginTransaction();
            try {
                DraftingKebijakan::create(array_merge($draftPayload, [
                    'request_kebijakan_id' => $record->id,
                    'status' => 'in_progress',
                    'processed_by' => $employee->nama_lengkap,
                    'processed_at' => Carbon::now(),
                ]));

                $record->update([
                    'status' => 'on_process',
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Draft kebijakan berhasil dibuat dan masuk ke tahap proses',
                ], 200);
            } catch (\Throwable $th) {
                DB::rollBack();

                return response()->json(['message' => $th->getMessage()], 500);
            }
        }

        if (!$record->drafting || $record->status !== 'on_process') {
            return response()->json(['message' => 'Draft kebijakan tidak ditemukan atau tidak dapat diubah'], 422);
        }

        if ($record->drafting->status === 'submitted') {
            return response()->json(['message' => 'Draft yang sudah diajukan tidak dapat diubah'], 422);
        }

        DB::beginTransaction();
        try {
            $record->drafting->update(array_merge($draftPayload, [
                'updated_by' => $employee->nama_lengkap,
                'updated_at' => Carbon::now(),
            ]));

            DB::commit();

            return response()->json([
                'message' => 'Draft kebijakan berhasil disimpan',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function previewPdf(Request $request)
    {
        $record = RequestKebijakan::with('drafting')->findOrFail($request->id);

        if (!$record->drafting) {
            return response()->json(['message' => 'Draft kebijakan belum tersedia'], 404);
        }

        $pdfString = app(RenderKebijakanDocumentPdf::class)->renderFromDraft($record->drafting);

        return response()->json([
            'data' => base64_encode($pdfString),
            'message' => 'PDF berhasil dibuat',
        ], 200);
    }

    public function submitDraft(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $record = RequestKebijakan::with('drafting')->findOrFail($request->id);

        if (!$record->drafting || $record->status !== 'on_process') {
            return response()->json(['message' => 'Draft kebijakan tidak ditemukan'], 422);
        }

        if ($record->drafting->status === 'submitted') {
            return response()->json(['message' => 'Draft kebijakan sudah pernah diajukan'], 422);
        }

        DB::beginTransaction();
        try {
            $record->drafting->update([
                'status' => 'submitted',
                'submitted_by' => $employee->nama_lengkap,
                'submitted_at' => Carbon::now(),
                'review_rejected_by' => null,
                'review_rejected_at' => null,
                'review_rejected_note' => null,
                'review_rejected_source' => null,
                'review_reject_used_user_note' => false,
                'updated_by' => $employee->nama_lengkap,
                'updated_at' => Carbon::now(),
            ]);

            $hasVerifiers = RequestKebijakanVerifierService::hasVerifiers($record);
            $reopenedRejected = false;

            if ($hasVerifiers) {
                $reopenedRejected = RequestKebijakanVerifierService::reopenRejectedAfterLegalResubmit($record);
            }

            if ($hasVerifiers && ($reopenedRejected || RequestKebijakanVerifierService::hasPendingVerifiers($record))) {
                $record->update([
                    'status' => 'pending_user_review',
                    'user_review_rejected_by' => null,
                    'user_review_rejected_at' => null,
                    'user_review_rejected_note' => null,
                    'user_reviewed_by' => null,
                    'user_reviewed_at' => null,
                ]);
            } else {
                $record->update([
                    'status' => 'completed',
                    'forwarded_to_user_by' => null,
                    'forwarded_to_user_at' => null,
                    'user_reviewed_by' => null,
                    'user_reviewed_at' => null,
                    'user_review_rejected_by' => null,
                    'user_review_rejected_at' => null,
                    'user_review_rejected_note' => null,
                ]);

                RequestKebijakanNotificationService::draftSubmitted($record->fresh(), $employee);
            }

            DB::commit();

            return response()->json([
                'message' => $hasVerifiers && RequestKebijakanVerifierService::hasPendingVerifiers($record->fresh())
                    ? 'Draft kebijakan berhasil diajukan ulang. Menunggu verifikasi reviewer yang ditolak.'
                    : 'Draft kebijakan berhasil diajukan. Tahap review berikutnya akan segera tersedia.',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}
