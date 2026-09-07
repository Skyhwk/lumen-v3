<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\RequestKebijakan;
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

        return response()->json([
            'data' => [
                'employee' => $employee,
                'can_approve' => RequestKebijakanWorkflowService::canApprove($employee),
            ],
            'message' => 'Request kebijakan approval initialized successfully',
        ], 200);
    }

    public function index(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        if (!RequestKebijakanWorkflowService::canApprove($employee)) {
            abort(403, $this->getAccessDeniedMessage());
        }

        $scope = $request->input('scope', 'pending');

        $query = RequestKebijakan::query()
            ->with(['requester.jabatan', 'requester.divisi'])
            ->where('is_active', true)
            ->orderByDesc('request_at');

        if ($scope === 'approved') {
            $query->whereIn('status', ['approved', 'on_process', 'completed']);
        } else {
            $query->where('status', 'waiting_approval');
        }

        return DataTables::of($query)
            ->addColumn('display_status', fn ($row) => RequestKebijakanWorkflowService::resolveDisplayStatus($row))
            ->addColumn('display_kategori', fn ($row) => RequestKebijakanWorkflowService::resolveKategoriLabel($row->kategori))
            ->addColumn('can_approve', fn () => RequestKebijakanWorkflowService::canApprove($employee))
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
        $employee = $request->attributes->get('user')->karyawan;

        if (!RequestKebijakanWorkflowService::canApprove($employee)) {
            abort(403, $this->getAccessDeniedMessage());
        }

        $record = RequestKebijakan::with(['requester.jabatan', 'requester.divisi', 'drafting'])
            ->findOrFail($request->id);

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

    public function process(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        if (!RequestKebijakanWorkflowService::canApprove($employee)) {
            abort(403, $this->getAccessDeniedMessage());
        }

        $record = RequestKebijakan::findOrFail($request->input('data.parent_id'));

        if ($record->status !== 'waiting_approval' || !$record->is_active) {
            return response()->json(['message' => 'Request tidak dapat diproses pada tahap ini'], 422);
        }

        DB::beginTransaction();
        try {
            if ($request->input('action') === 'approve') {
                $record->update([
                    'status' => 'approved',
                    'approval_by' => $employee->nama_lengkap,
                    'approval_at' => Carbon::now(),
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejected_note' => null,
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Request kebijakan berhasil disetujui dan diteruskan ke tim legal untuk drafting',
                ], 200);
            }

            if ($request->input('action') === 'reject') {
                $reason = trim((string) ($request->input('data.reason') ?? ''));

                if ($reason === '') {
                    return response()->json(['message' => 'Alasan penolakan wajib diisi'], 422);
                }

                $record->update([
                    'status' => 'rejected',
                    'rejected_by' => $employee->nama_lengkap,
                    'rejected_at' => Carbon::now(),
                    'rejected_note' => $reason,
                ]);

                DB::commit();

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

    private function getAccessDeniedMessage(): string
    {
        return 'Maaf, Anda tidak memiliki otorisasi untuk melakukan approval request kebijakan. '
            . 'Fitur ini hanya tersedia bagi karyawan dengan grade Manager, Senior Manager, Executive, dan Director.';
    }
}
