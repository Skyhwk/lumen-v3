<?php

namespace App\Services;

use App\Models\ClaimFeeExternal;
use App\Support\ClaimFeeExternal\ClaimFeeExternalWorkflow;

class ClaimFeeExternalStatusSyncService
{
    public function sync(ClaimFeeExternal $claim): void
    {
        $sales = $claim->sales_workflow_status ?? null;
        $finance = $claim->finance_status ?? null;

        if ($finance !== null && $finance !== '') {
            switch ($finance) {
                case ClaimFeeExternalWorkflow::FINANCE_WAITING_TAX:
                    $claim->status_pembayaran = 'WAITING PROCESS';
                    break;
                case ClaimFeeExternalWorkflow::FINANCE_TAX_IN_PROGRESS:
                    $claim->status_pembayaran = 'PROCESSED';
                    break;
                case ClaimFeeExternalWorkflow::FINANCE_READY_TO_PAY:
                    $claim->status_pembayaran = 'READY TO TRANSFER';
                    break;
                case ClaimFeeExternalWorkflow::FINANCE_PAID:
                    $claim->status_pembayaran = 'TRANSFER';
                    break;
                case ClaimFeeExternalWorkflow::FINANCE_REJECTED:
                    $claim->status_pembayaran = 'REJECTED';
                    break;
            }

            if ($finance === ClaimFeeExternalWorkflow::FINANCE_WAITING_TAX) {
                $claim->is_approved_manajer = true;
            }

            return;
        }

        switch ($sales) {
            case ClaimFeeExternalWorkflow::SALES_SUBMITTED:
            case ClaimFeeExternalWorkflow::SALES_PENDING_SPV:
            case ClaimFeeExternalWorkflow::SALES_PENDING_MANAGER:
            case ClaimFeeExternalWorkflow::SALES_PENDING_SENIOR_MANAGER:
            case ClaimFeeExternalWorkflow::SALES_APPROVED:
                $claim->status_pembayaran = 'WAITING PROCESS';
                break;
            case ClaimFeeExternalWorkflow::SALES_REJECTED:
                $claim->status_pembayaran = 'REJECTED';
                break;
            default:
                if ($claim->status_pembayaran === null || $claim->status_pembayaran === '') {
                    $claim->status_pembayaran = 'WAITING PROCESS';
                }
                break;
        }
    }
}