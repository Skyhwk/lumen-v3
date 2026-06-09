<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillPoStatusForProcessedDocuments extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('purchase_order_documents', 'po_status')) {
            return;
        }

        DB::table('purchase_order_documents as pod')
            ->join('purchase_requests as pr', 'pr.id', '=', 'pod.purchase_request_id')
            ->whereIn('pr.finance_status', [
                'Waiting Vendor Receipt',
                'Waiting User Receipt',
                'Distributing',
                'Distributed',
            ])
            ->where(function ($query) {
                $query->where('pod.is_voided', false)->orWhereNull('pod.is_voided');
            })
            ->where('pod.po_status', 'draft')
            ->update([
                'pod.po_status' => 'active',
                'pod.processed_by' => DB::raw('pr.po_approved_by'),
                'pod.processed_at' => DB::raw('pr.po_approved_at'),
            ]);
    }

    public function down()
    {
        // no rollback
    }
}
