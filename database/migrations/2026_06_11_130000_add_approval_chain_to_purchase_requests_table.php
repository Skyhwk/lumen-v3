<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalChainToPurchaseRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requests', 'approval_step')) {
                $table->unsignedTinyInteger('approval_step')->default(0)->after('rejection_note');
            }
            if (!Schema::hasColumn('purchase_requests', 'approval_chain')) {
                $table->json('approval_chain')->nullable()->after('approval_step');
            }
            if (!Schema::hasColumn('purchase_requests', 'approval_log')) {
                $table->json('approval_log')->nullable()->after('approval_chain');
            }
        });
    }

    public function down()
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            foreach (['approval_step', 'approval_chain', 'approval_log'] as $column) {
                if (Schema::hasColumn('purchase_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
