<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddTanggalKedatanganAndGoodsVoidToPurchaseRequests extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE purchase_requests MODIFY finance_status ENUM(
            'Waiting to Delegate',
            'Waiting to Create PO',
            'PO Created',
            'Rejected',
            'Waiting Process',
            'On Process',
            'Pending',
            'Waiting Vendor Receipt',
            'Waiting User Receipt',
            'Distributing',
            'Distributed',
            'Void'
        ) NULL");

        Schema::table('purchase_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requests', 'tanggal_kedatangan')) {
                $table->date('tanggal_kedatangan')->nullable()->after('purpose');
            }
            if (!Schema::hasColumn('purchase_requests', 'is_goods_voided')) {
                $table->boolean('is_goods_voided')->default(false)->after('user_confirmed_total');
            }
            if (!Schema::hasColumn('purchase_requests', 'goods_voided_by')) {
                $table->string('goods_voided_by', 255)->nullable()->after('is_goods_voided');
            }
            if (!Schema::hasColumn('purchase_requests', 'goods_voided_at')) {
                $table->dateTime('goods_voided_at')->nullable()->after('goods_voided_by');
            }
            if (!Schema::hasColumn('purchase_requests', 'goods_void_note')) {
                $table->text('goods_void_note')->nullable()->after('goods_voided_at');
            }
        });

        Schema::table('purchase_receipt_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_receipt_batches', 'user_confirm_note')) {
                $table->text('user_confirm_note')->nullable()->after('user_receipt_at');
            }
        });
    }

    public function down()
    {
        Schema::table('purchase_receipt_batches', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_receipt_batches', 'user_confirm_note')) {
                $table->dropColumn('user_confirm_note');
            }
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $columns = ['tanggal_kedatangan', 'is_goods_voided', 'goods_voided_by', 'goods_voided_at', 'goods_void_note'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('purchase_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        DB::statement("UPDATE purchase_requests SET finance_status = 'Distributing' WHERE finance_status = 'Void'");

        DB::statement("ALTER TABLE purchase_requests MODIFY finance_status ENUM(
            'Waiting to Delegate',
            'Waiting to Create PO',
            'PO Created',
            'Rejected',
            'Waiting Process',
            'On Process',
            'Pending',
            'Waiting Vendor Receipt',
            'Waiting User Receipt',
            'Distributing',
            'Distributed'
        ) NULL");
    }
}
