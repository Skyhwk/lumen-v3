<?php

use App\Models\PurchaseRequest;
use App\Services\PurchaseRequestApprovalService;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillPurchaseRequestPurchasingApproval extends Migration
{
    private const BACKFILL_MARKER = 'purchasing_approval_backfill';

    public function up()
    {
        $dryRun = filter_var(getenv('BACKFILL_DRY_RUN') ?: 'false', FILTER_VALIDATE_BOOLEAN);
        $affectedIds = $this->getAffectedPurchaseRequestIds();

        if ($affectedIds->isEmpty()) {
            Log::info('[purchase-backfill] Tidak ada PR yang perlu di-backfill.');
            echo "[purchase-backfill] Tidak ada PR yang perlu di-backfill.\n";

            return;
        }

        $updated = 0;
        $preview = [];

        DB::beginTransaction();

        try {
            foreach ($affectedIds as $purchaseRequestId) {
                $purchaseRequest = PurchaseRequest::find($purchaseRequestId);

                if (!$purchaseRequest || $purchaseRequest->delegated_at) {
                    continue;
                }

                $firstPo = DB::table('purchase_order_documents')
                    ->where('purchase_request_id', $purchaseRequest->id)
                    ->where(function ($query) {
                        $query->where('is_voided', false)->orWhereNull('is_voided');
                    })
                    ->orderBy('id')
                    ->first(['created_by', 'created_at']);

                $delegatedBy = $this->resolveDelegatedBy($purchaseRequest, $firstPo);
                $delegatedAt = $this->resolveDelegatedAt($purchaseRequest, $firstPo);

                $preview[] = [
                    'id' => $purchaseRequest->id,
                    'request_number' => $purchaseRequest->request_number,
                    'finance_status' => $purchaseRequest->finance_status,
                    'delegated_by' => $delegatedBy,
                    'delegated_at' => $delegatedAt,
                ];

                if ($dryRun) {
                    continue;
                }

                $log = PurchaseRequestApprovalService::parseLog($purchaseRequest->approval_log);
                $log[] = [
                    'type' => self::BACKFILL_MARKER,
                    'by' => $delegatedBy,
                    'at' => $delegatedAt,
                    'note' => 'Retroactive purchasing approval backfill (validation bypass before PO gate fix)',
                    'migration' => '2026_06_25_180000_backfill_purchase_request_purchasing_approval',
                ];

                $purchaseRequest->delegated_by = $delegatedBy;
                $purchaseRequest->delegated_at = $delegatedAt;
                $purchaseRequest->approval_log = PurchaseRequestApprovalService::encodeLog($log);
                $purchaseRequest->save();

                $updated++;
            }

            if ($dryRun) {
                DB::rollBack();
                echo "[purchase-backfill] DRY RUN — tidak ada perubahan disimpan.\n";
            } else {
                DB::commit();
                echo "[purchase-backfill] Berhasil memperbarui {$updated} PR.\n";
            }

            foreach ($preview as $row) {
                echo sprintf(
                    "- %s (#%d) %s => delegated_by=%s, delegated_at=%s\n",
                    $row['request_number'],
                    $row['id'],
                    $row['finance_status'],
                    $row['delegated_by'],
                    $row['delegated_at']
                );
            }

            Log::info('[purchase-backfill] Selesai', [
                'dry_run' => $dryRun,
                'updated' => $updated,
                'preview' => $preview,
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    public function down()
    {
        $purchaseRequests = PurchaseRequest::query()
            ->whereNotNull('delegated_at')
            ->get();

        $reverted = 0;

        foreach ($purchaseRequests as $purchaseRequest) {
            $log = PurchaseRequestApprovalService::parseLog($purchaseRequest->approval_log);
            $hasMarker = false;

            $filteredLog = array_values(array_filter($log, function ($entry) use (&$hasMarker) {
                if (($entry['type'] ?? null) === self::BACKFILL_MARKER) {
                    $hasMarker = true;

                    return false;
                }

                return true;
            }));

            if (!$hasMarker) {
                continue;
            }

            $purchaseRequest->delegated_by = null;
            $purchaseRequest->delegated_at = null;
            $purchaseRequest->approval_log = PurchaseRequestApprovalService::encodeLog($filteredLog);
            $purchaseRequest->save();

            $reverted++;
        }

        echo "[purchase-backfill] Rollback: {$reverted} PR dikembalikan.\n";
    }

    private function getAffectedPurchaseRequestIds()
    {
        return DB::table('purchase_requests as pr')
            ->where('pr.is_active', true)
            ->whereNull('pr.delegated_at')
            ->where('pr.finance_status', '!=', 'Rejected')
            ->where(function ($query) {
                $query->whereNotIn('pr.finance_status', ['Waiting to Delegate'])
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('purchase_order_documents as pod')
                            ->whereColumn('pod.purchase_request_id', 'pr.id')
                            ->where(function ($podQuery) {
                                $podQuery->where('pod.is_voided', false)->orWhereNull('pod.is_voided');
                            })
                            ->whereIn('pod.po_status', ['draft', 'active']);
                    });
            })
            ->orderBy('pr.id')
            ->pluck('pr.id');
    }

    private function resolveDelegatedBy(PurchaseRequest $purchaseRequest, $firstPo): string
    {
        $candidates = array_filter([
            $purchaseRequest->po_created_by,
            $firstPo->created_by ?? null,
            $purchaseRequest->po_approved_by,
            $purchaseRequest->processed_by,
        ]);

        return $candidates ? (string) reset($candidates) : 'System Backfill';
    }

    private function resolveDelegatedAt(PurchaseRequest $purchaseRequest, $firstPo): string
    {
        $approvedAt = $this->toTimestamp($purchaseRequest->approved_at);
        $poCreatedAt = $this->toTimestamp($purchaseRequest->po_created_at)
            ?? $this->toTimestamp($firstPo->created_at ?? null);
        $createdAt = $this->toTimestamp($purchaseRequest->created_at ?? null);

        if ($approvedAt && $poCreatedAt) {
            if ($approvedAt >= $poCreatedAt) {
                return Carbon::createFromTimestamp($poCreatedAt - 60)->format('Y-m-d H:i:s');
            }

            $midpoint = $approvedAt + (int) floor(($poCreatedAt - $approvedAt) / 2);

            return Carbon::createFromTimestamp(max($midpoint, $approvedAt + 60))->format('Y-m-d H:i:s');
        }

        if ($poCreatedAt) {
            return Carbon::createFromTimestamp($poCreatedAt - 3600)->format('Y-m-d H:i:s');
        }

        if ($approvedAt) {
            return Carbon::createFromTimestamp($approvedAt + 3600)->format('Y-m-d H:i:s');
        }

        if ($createdAt) {
            return Carbon::createFromTimestamp($createdAt + 3600)->format('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s');
    }

    private function toTimestamp($value): ?int
    {
        if (empty($value)) {
            return null;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp ?: null;
    }
}
