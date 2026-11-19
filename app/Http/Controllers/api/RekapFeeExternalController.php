<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\ClaimFeeExternalFinanceDataTable;
use App\Http\Controllers\api\Concerns\ScopesClaimFeeExternalRekap;
use App\Models\ClaimFeeExternal;
use App\Services\ClaimFeeExternalSalesWorkflowService;
use Illuminate\Http\Request;

/**
 * Laporan finance — akses via menu/ACL saja; tidak ada filter hierarki sales (view all data rekap).
 */
class RekapFeeExternalController extends Controller
{
    use ClaimFeeExternalFinanceDataTable;
    use ScopesClaimFeeExternalRekap;

    public function index(Request $request)
    {
        $query = ClaimFeeExternal::query()->where('is_active', true);
        $this->applyClaimFeeExternalRekapScope($query);

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
        $this->applyClaimFeeExternalRekapScope($visible);

        if (!$visible->exists()) {
            return response()->json([
                'message' => 'Data belum masuk rekap (belum tercatat transfer / tanggal pembayaran).',
            ], 403);
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
}