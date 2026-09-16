<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\KebijakanDokumen;
use App\Services\KebijakanDokumenQrService;
use App\Services\KebijakanDokumenService;
use App\Services\RenderKebijakanDocumentPdf;
use App\Services\RequestKebijakanNotificationService;
use App\Services\RequestKebijakanWorkflowService;
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KebijakanDokumenController extends Controller
{
    public function initialize(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $canView = RequestKebijakanNotificationService::canAccessApprovalMenu($employee)
            || KebijakanDokumenService::canDirectorAct($employee)
            || RequestKebijakanWorkflowService::canApprove($employee);

        return response()->json([
            'data' => [
                'employee' => $employee,
                'can_view' => $canView,
                'can_director' => KebijakanDokumenService::canDirectorAct($employee),
                'can_manual_create' => KebijakanDokumenService::canManualCreate($employee),
                'is_programmer' => KebijakanDokumenService::isProgrammer($employee),
                'counts' => $canView ? KebijakanDokumenService::getTabCounts() : [],
            ],
            'message' => 'Dokumen ketetapan perusahaan initialized successfully',
        ], 200);
    }

    public function counts(Request $request)
    {
        return response()->json([
            'data' => [
                'counts' => KebijakanDokumenService::getTabCounts(),
            ],
            'message' => 'Tab counts retrieved successfully',
        ], 200);
    }

    public function index(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $scope = $request->input('scope', 'pending_director');
        $query = KebijakanDokumenService::buildScopeQuery($scope);
        $globalSearch = trim((string) $request->input('global_search', ''));
        $query = KebijakanDokumenService::applyGlobalSearchToQuery($query, $globalSearch);
        $canDirector = KebijakanDokumenService::canDirectorAct($employee);

        return DataTables::of($query)
            ->addColumn('divisi_bagian', fn ($row) => optional($row->drafting)->divisi_bagian ?: '-')
            ->addColumn('display_archive_reason', fn ($row) => KebijakanDokumenService::resolveArchiveReasonLabel($row->archive_reason))
            ->addColumn('request_by', fn ($row) => optional($row->requestKebijakan)->request_by)
            ->addColumn('request_at', fn ($row) => optional($row->requestKebijakan)->request_at)
            ->addColumn('terbitan', fn ($row) => $row->cetakan)
            ->addColumn('tanggal_terbit', fn ($row) => $row->tanggal_pengesahan)
            ->addColumn('can_director_act', fn () => $canDirector)
            ->addColumn('is_manual', fn ($row) => KebijakanDokumenService::isManualDocument($row))
            ->addColumn('can_manual_update', fn ($row) => KebijakanDokumenService::canUpdateManualDocument($employee, $row))
            ->filterColumn('no_dokumen', fn ($q, $keyword) => $q->where('kebijakan_dokumen.no_dokumen', 'like', "%{$keyword}%"))
            ->filterColumn('divisi_bagian', function ($q, $keyword) {
                $q->whereHas('drafting', fn ($sub) => $sub->where('divisi_bagian', 'like', "%{$keyword}%"));
            })
            ->filterColumn('judul', fn ($q, $keyword) => $q->where('kebijakan_dokumen.judul', 'like', "%{$keyword}%"))
            ->filterColumn('request_by', function ($q, $keyword) {
                $q->whereHas('requestKebijakan', fn ($sub) => $sub->where('request_by', 'like', "%{$keyword}%"));
            })
            ->filterColumn('request_at', function ($q, $keyword) {
                $q->whereHas('requestKebijakan', fn ($sub) => $sub->where('request_at', 'like', "%{$keyword}%"));
            })
            ->filterColumn('terbitan', fn ($q, $keyword) => $q->where('kebijakan_dokumen.cetakan', 'like', "%{$keyword}%"))
            ->filterColumn('tanggal_terbit', fn ($q, $keyword) => $q->where('kebijakan_dokumen.tanggal_pengesahan', 'like', "%{$keyword}%"))
            ->filterColumn('archive_reason', function ($q, $keyword) {
                $keyword = strtolower(trim($keyword));
                $q->where(function ($sub) use ($keyword) {
                    foreach (KebijakanDokumenService::getArchiveReasonFilterMap() as $dbValue => $label) {
                        if (strpos(strtolower($label), $keyword) !== false || strpos(strtolower($dbValue), $keyword) !== false) {
                            $sub->orWhere('kebijakan_dokumen.archive_reason', $dbValue);
                        }
                    }
                    $sub->orWhere('kebijakan_dokumen.archive_reason', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('archived_at', fn ($q, $keyword) => $q->where('kebijakan_dokumen.archived_at', 'like', "%{$keyword}%"))
            ->make(true);
    }

    public function previewPdf(Request $request)
    {
        $dokumen = KebijakanDokumen::with('drafting')->findOrFail($request->id);

        if (!$dokumen->drafting) {
            return response()->json(['message' => 'Draft kebijakan belum tersedia'], 404);
        }

        if (!$dokumen->legal_verified_at) {
            return response()->json(['message' => 'Dokumen belum diverifikasi final legal'], 422);
        }

        $dokumen = KebijakanDokumenQrService::ensureQrReady($dokumen);
        $pdfString = app(RenderKebijakanDocumentPdf::class)->renderFromDokumen($dokumen);

        return response()->json([
            'data' => base64_encode($pdfString),
            'message' => 'PDF berhasil dibuat',
        ], 200);
    }

    public function manualCreateOptions(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        if (!KebijakanDokumenService::canManualCreate($employee)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses membuat ketetapan perusahaan secara manual',
            ], 403);
        }

        return response()->json([
            'data' => KebijakanDokumenService::getManualCreateOptions(),
            'message' => 'Opsi create manual berhasil diambil',
        ], 200);
    }

    public function showManual(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $dokumen = KebijakanDokumen::with(['drafting', 'requestKebijakan'])->findOrFail($request->id);

        if (!KebijakanDokumenService::canUpdateManualDocument($employee, $dokumen)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses mengubah ketetapan manual ini',
            ], 403);
        }

        return response()->json([
            'data' => KebijakanDokumenService::buildManualEditPayload($dokumen),
            'message' => 'Detail ketetapan manual berhasil diambil',
        ], 200);
    }

    public function updateManual(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $dokumenId = (int) $request->input('id', 0);

        if ($dokumenId <= 0) {
            return response()->json(['message' => 'ID dokumen wajib diisi'], 422);
        }

        DB::beginTransaction();
        try {
            $dokumen = KebijakanDokumenService::updateManualActiveDocument($employee, $dokumenId, $request->all());

            DB::commit();

            return response()->json([
                'message' => 'Ketetapan manual berhasil diperbarui',
                'data' => [
                    'id' => $dokumen->id,
                    'no_dokumen' => $dokumen->no_dokumen,
                    'revisian' => $dokumen->revisian,
                ],
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 422);
        }
    }

    public function storeManual(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        if (!KebijakanDokumenService::canManualCreate($employee)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses membuat ketetapan perusahaan secara manual',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $dokumen = KebijakanDokumenService::createManualActiveDocument($employee, $request->all());

            DB::commit();

            return response()->json([
                'message' => 'Ketetapan perusahaan berhasil dibuat secara manual',
                'data' => [
                    'id' => $dokumen->id,
                    'no_dokumen' => $dokumen->no_dokumen,
                    'revisian' => $dokumen->revisian,
                ],
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 422);
        }
    }

    public function process(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $action = $request->input('action');
        $dokumenId = $request->input('data.parent_id');

        $dokumen = KebijakanDokumen::findOrFail($dokumenId);

        DB::beginTransaction();
        try {
            if ($action === 'director_approve') {
                if (!KebijakanDokumenService::canDirectorAct($employee)) {
                    return response()->json(['message' => 'Hanya Director yang dapat mengesahkan dokumen'], 403);
                }

                KebijakanDokumenService::approveByDirector($dokumen, $employee);

                DB::commit();

                return response()->json(['message' => 'Dokumen ketetapan berhasil disahkan dan dinyatakan aktif'], 200);
            }

            if ($action === 'director_reject') {
                if (!KebijakanDokumenService::canDirectorAct($employee)) {
                    return response()->json(['message' => 'Hanya Director yang dapat menolak pengesahan dokumen'], 403);
                }

                $reason = trim((string) ($request->input('data.reason') ?? ''));

                if ($reason === '' || trim(strip_tags($reason)) === '') {
                    return response()->json(['message' => 'Alasan penolakan wajib diisi'], 422);
                }

                KebijakanDokumenService::rejectByDirector($dokumen, $employee, $reason);

                DB::commit();

                return response()->json(['message' => 'Penolakan pengesahan berhasil. Dokumen kembali ke verifikasi final legal.'], 200);
            }

            DB::rollBack();

            return response()->json(['message' => 'Aksi tidak valid'], 422);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}
