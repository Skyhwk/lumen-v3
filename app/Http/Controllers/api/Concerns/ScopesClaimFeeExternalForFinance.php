<?php

namespace App\Http\Controllers\api\Concerns;

use App\Models\ClaimFeeExternal;
use App\Support\ClaimFeeExternal\ClaimFeeExternalWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait ScopesClaimFeeExternalForFinance
{
    protected function applyFinanceSalesGate(Builder $query): Builder
    {
        if (Schema::hasColumn('claim_fee_external', 'sales_workflow_status')) {
            $query->where(
                'sales_workflow_status',
                ClaimFeeExternalWorkflow::SALES_APPROVED
            );
        } else {
            $query->where('is_approved_manajer', true);
        }

        return $query;
    }

    protected function assertFinanceMutable(ClaimFeeExternal $claim): void
    {
        if (!Schema::hasColumn('claim_fee_external', 'sales_workflow_status')) {
            if (!(bool) $claim->is_approved_manajer) {
                throw new \RuntimeException('Claim belum disetujui sales.');
            }

            return;
        }

        if (($claim->sales_workflow_status ?? '') !== ClaimFeeExternalWorkflow::SALES_APPROVED) {
            throw new \RuntimeException('Claim belum disetujui seluruh rantai sales.');
        }
    }

    protected function setFinanceStatus(ClaimFeeExternal $claim, string $financeStatus): void
    {
        if (Schema::hasColumn('claim_fee_external', 'finance_status')) {
            $claim->finance_status = $financeStatus;
        }
        app(\App\Services\ClaimFeeExternalStatusSyncService::class)->sync($claim);
    }
}