<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClaimFeeExternal;
use App\Models\OrderHeader;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Http\File;
use App\Http\Controllers\api\Concerns\ClaimFeeExternalFinanceDataTable;
use App\Http\Controllers\api\Concerns\ScopesClaimFeeExternalForFinance;
use App\Support\ClaimFeeExternal\ClaimFeeExternalWorkflow;
use App\Services\ClaimFeeExternalNotificationService;
use App\Services\ClaimFeeExternalSalesWorkflowService;

class ClaimFeeExternalTaxController extends Controller
{
    use ScopesClaimFeeExternalForFinance;
    use ClaimFeeExternalFinanceDataTable;

    public function outstandingIndex(Request $request)
    {
        $query = ClaimFeeExternal::query()
            ->where('is_active', true);
        $this->applyFinanceSalesGate($query);
        $query->whereIn('status_pembayaran', ['PROCESSED', 'WAITING PROCESS']);

        return $this->makeClaimFeeExternalFinanceDataTable($query, $request);
    }

    public function settlementIndex(Request $request)
    {
        $query = ClaimFeeExternal::query()
            ->where('is_active', true);
        $this->applyFinanceSalesGate($query);
        $query->whereIn('status_pembayaran', ['READY TO TRANSFER', 'TRANSFER', 'REJECTED']);

        return $this->makeClaimFeeExternalFinanceDataTable($query, $request);
    }

    public function show(Request $request)
    {
        $claim = ClaimFeeExternal::find($request->id);
        if (!$claim || !$claim->is_active) {
            return response()->json(['message' => 'Claim tidak ditemukan'], 404);
        }

        $visible = ClaimFeeExternal::query()
            ->where('id', $claim->id)
            ->where('is_active', true);
        $this->applyFinanceSalesGate($visible);

        if (!$visible->exists()) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $workflow = app(ClaimFeeExternalSalesWorkflowService::class);

        return response()->json([
            'claim' => $claim->fresh(),
            'pipeline' => $workflow->workflowEnabled() ? $workflow->buildPipeline($claim) : [],
            'permissions' => [
                'can_approve' => false,
                'can_reject' => false,
            ],
        ]);
    }

    public function approve(Request $request)
    {
        $claim = ClaimFeeExternal::find($request->id);
        if (!$claim) {
            return response()->json(['message' => 'Claim Fee External tidak ditemukan'], 404);
        }

        try {
            $this->assertFinanceMutable($claim);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $claim->processed_by = $this->karyawan;
        $claim->processed_at = Carbon::now()->format('Y-m-d H:i:s');
        $this->setFinanceStatus($claim, ClaimFeeExternalWorkflow::FINANCE_TAX_IN_PROGRESS);
        $claim->save();

        app(ClaimFeeExternalNotificationService::class)->notifySubmitter(
            'CFE_FINANCE_TAX_IN_PROGRESS',
            $claim
        );

        return response()->json(['message' => 'Claim Fee External Berhasil Disetujui']);
    }

    public function setPPH(Request $request)
    {
        $claim = ClaimFeeExternal::find($request->id);
        if (!$claim) {
            return response()->json(['message' => 'Claim Fee External tidak ditemukan'], 404);
        }

        try {
            $this->assertFinanceMutable($claim);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if (!$claim->processed_at) {
            $claim->processed_by = $this->karyawan;
            $claim->processed_at = Carbon::now()->format('Y-m-d H:i:s');
        }

        $claim->potongan = $request->potongan;
        $claim->nominal_bayar = $request->nominal_bayar;
        $this->setFinanceStatus($claim, ClaimFeeExternalWorkflow::FINANCE_READY_TO_PAY);
        $claim->save();

        app(ClaimFeeExternalNotificationService::class)->notifySubmitter(
            'CFE_FINANCE_READY_TO_PAY',
            $claim
        );

        return response()->json(['message' => 'PPH External Berhasil di Set']);
    }

    public function reject(Request $request)
    {
        $claim = ClaimFeeExternal::find($request->id);
        if (!$claim) {
            return response()->json(['message' => 'Claim Fee External tidak ditemukan'], 404);
        }

        try {
            $this->assertFinanceMutable($claim);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $claim->rejected_by = $this->karyawan;
        $claim->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
        $this->setFinanceStatus($claim, ClaimFeeExternalWorkflow::FINANCE_REJECTED);
        $claim->save();

        app(ClaimFeeExternalNotificationService::class)->notifySubmitter(
            'CFE_FINANCE_REJECTED',
            $claim,
            ['remarks' => (string) $request->input('remarks', '')]
        );

        return response()->json(['message' => 'Claim Fee External Berhasil Ditolak']);
    }

    public function uploadFile(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file('file_input');

            if (!$file || !$file->isValid()) {
                return response()->json(['error' => 'File upload tidak valid.'], 400);
            }

            $ext = strtolower($file->getClientOriginalExtension());
            if ($ext !== 'pdf') {
                return response()->json(['error' => 'File tidak valid. Harus .pdf'], 400);
            }

            $claim = ClaimFeeExternal::find($request->id);
            if (!$claim) {
                return response()->json(['error' => 'Claim Fee External tidak ditemukan'], 404);
            }

            $folder = public_path('claim_fee_external');
            if (!is_dir($folder)) {
                if (!mkdir($folder, 0775, true) && !is_dir($folder)) {
                    return response()->json([
                        'error' => 'Gagal membuat folder penyimpanan.',
                        'message' => $folder,
                    ], 500);
                }
            }

            $periodeSuffix = !empty($claim->periode) ? '-' . $claim->periode : '';
            $fileName = $claim->no_order . $periodeSuffix . '.pdf';
            $targetPath = $folder . DIRECTORY_SEPARATOR . $fileName;

            if (is_file($targetPath)) {
                @unlink($targetPath);
            }

            if (!$file->move($folder, $fileName)) {
                return response()->json([
                    'error' => 'Gagal menyimpan file.',
                    'message' => 'Pastikan folder claim_fee_external dapat ditulis.',
                ], 500);
            }

            if (Schema::hasColumn('claim_fee_external', 'filename')) {
                $claim->filename = $fileName;
            }
            $claim->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bukti potong PPh berhasil diunggah.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Terjadi kesalahan server',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
