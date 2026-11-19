<?php
namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClaimFeeExternal;
use App\Models\OrderHeader;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\api\Concerns\ClaimFeeExternalFinanceDataTable;
use App\Http\Controllers\api\Concerns\ScopesClaimFeeExternalForFinance;
use App\Http\Controllers\api\Concerns\ScopesClaimFeeExternalRekap;
use App\Support\ClaimFeeExternal\ClaimFeeExternalWorkflow;
use App\Services\ClaimFeeExternalNotificationService;
use App\Services\ClaimFeeExternalSalesWorkflowService;
use Illuminate\Support\Facades\Schema;


class ClaimFeeExternalExpenseController extends Controller
{
    use ScopesClaimFeeExternalForFinance;
    use ScopesClaimFeeExternalRekap;
    use ClaimFeeExternalFinanceDataTable;

    private const BUKTI_TRANSFER_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function outstandingIndex(Request $request)
    {
        $query = ClaimFeeExternal::query()
            ->where('is_active', true);
        $this->applyFinanceSalesGate($query);
        $query->where('status_pembayaran', 'READY TO TRANSFER')->whereNull('transferred_by');

        return $this->makeClaimFeeExternalFinanceDataTable($query, $request);
    }

    public function settlementIndex(Request $request)
    {
        $query = ClaimFeeExternal::query()
            ->where('is_active', true);
        $this->applyFinanceSalesGate($query);
        $this->applyClaimFeeExternalPendingBuktiTransferOnlyScope($query);

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

    public function transfer(Request $request)
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

        DB::beginTransaction();
        try {
            $claim->transferred_by = $this->karyawan;
            $claim->tanggal_pembayaran = $request->transfer_date;

            $file = $request->file('file_input');
            if ($file && $file->isValid() && $this->isAllowedBuktiTransferFile($file)) {
                $saved = $this->storeBuktiTransferFile($claim, $file);
                if (Schema::hasColumn('claim_fee_external', 'bukti_transfer')) {
                    $claim->bukti_transfer = $saved;
                }
            }

            $this->setFinanceStatus($claim, ClaimFeeExternalWorkflow::FINANCE_PAID);
            $claim->save();

            DB::commit();

            app(ClaimFeeExternalNotificationService::class)->notifySubmitter(
                'CFE_FINANCE_PAID',
                $claim
            );

            return response()->json(['message' => 'Claim Fee External berhasil ditandai transfer.']);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memproses transfer.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadBuktiTransfer(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file('file_input');

            if (!$file || !$file->isValid()) {
                return response()->json(['error' => 'File upload tidak valid.'], 400);
            }

            if (!$this->isAllowedBuktiTransferFile($file)) {
                return response()->json([
                    'error' => 'File harus PDF atau gambar (.jpg, .jpeg, .png, .gif, .webp).',
                ], 400);
            }

            $claim = ClaimFeeExternal::find($request->id);
            if (!$claim) {
                return response()->json(['error' => 'Claim tidak ditemukan'], 404);
            }

            if ($claim->status_pembayaran !== 'TRANSFER') {
                return response()->json(['error' => 'Claim belum berstatus transfer.'], 422);
            }

            if (!Schema::hasColumn('claim_fee_external', 'bukti_transfer')) {
                return response()->json(['error' => 'Kolom bukti_transfer belum tersedia di database.'], 500);
            }

            $fileName = $this->storeBuktiTransferFile($claim, $file);
            $claim->bukti_transfer = $fileName;
            $claim->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bukti transfer berhasil diunggah.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Terjadi kesalahan server',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function isAllowedBuktiTransferFile($file): bool
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());

        return in_array($ext, self::BUKTI_TRANSFER_EXTENSIONS, true);
    }

    private function storeBuktiTransferFile(ClaimFeeExternal $claim, $file): string
    {
        $folder = public_path('claim_fee_external' . DIRECTORY_SEPARATOR . 'bukti_transfer');
        if (!is_dir($folder)) {
            if (!mkdir($folder, 0775, true) && !is_dir($folder)) {
                throw new \RuntimeException('Gagal membuat folder bukti transfer.');
            }
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($ext, self::BUKTI_TRANSFER_EXTENSIONS, true)) {
            throw new \RuntimeException('Ekstensi bukti transfer tidak diizinkan.');
        }

        if (!empty($claim->bukti_transfer)) {
            $existingPath = $folder . DIRECTORY_SEPARATOR . $claim->bukti_transfer;
            if (is_file($existingPath)) {
                @unlink($existingPath);
            }
        }

        $periodeSuffix = !empty($claim->periode) ? '-' . $claim->periode : '';
        $fileName = $claim->no_order . $periodeSuffix . '-bukti-transfer.' . $ext;
        $targetPath = $folder . DIRECTORY_SEPARATOR . $fileName;

        if (is_file($targetPath)) {
            @unlink($targetPath);
        }

        if (!$file->move($folder, $fileName)) {
            throw new \RuntimeException('Gagal menyimpan file bukti transfer.');
        }

        return $fileName;
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

}