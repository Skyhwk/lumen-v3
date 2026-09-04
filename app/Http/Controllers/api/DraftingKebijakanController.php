<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DraftingKebijakan;
use App\Models\RequestKebijakan;
use App\Services\RequestKebijakanWorkflowService;
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
        if ($judul === '') {
            return response()->json(['message' => 'Judul draft wajib diisi'], 422);
        }

        $draftPayload = [
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
                'updated_by' => $employee->nama_lengkap,
                'updated_at' => Carbon::now(),
            ]);

            $record->update([
                'status' => 'completed',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Draft kebijakan berhasil diajukan. Tahap review berikutnya akan segera tersedia.',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}
