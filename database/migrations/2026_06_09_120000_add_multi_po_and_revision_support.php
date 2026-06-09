<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddMultiPoAndRevisionSupport extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('purchase_order_documents', 'revision_no')) {
            Schema::table('purchase_order_documents', function (Blueprint $table) {
                $table->unsignedInteger('revision_no')->default(1)->after('void_from_finance_status');
                $table->string('po_status', 30)->default('draft')->after('revision_no');
                $table->string('processed_by', 255)->nullable()->after('po_status');
                $table->dateTime('processed_at')->nullable()->after('processed_by');
            });
        }

        if (Schema::hasColumn('purchase_order_documents', 'po_status')) {
            DB::table('purchase_order_documents')
                ->where(function ($query) {
                    $query->where('is_voided', true)->orWhereNotNull('voided_at');
                })
                ->update(['po_status' => 'voided']);

            DB::table('purchase_order_documents')
                ->where(function ($query) {
                    $query->where('is_voided', false)->orWhereNull('is_voided');
                })
                ->whereNull('voided_at')
                ->where(function ($query) {
                    $query->whereNull('po_status')->orWhere('po_status', 'draft');
                })
                ->update(['po_status' => 'draft']);
        }

        if (Schema::hasTable('purchase_order_document_revisions')) {
            return;
        }

        Schema::create('purchase_order_document_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_document_id');
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedInteger('revision_no');
            $table->string('po_number', 100);
            $table->string('supplier_name', 255)->nullable();
            $table->decimal('quantity', 15, 2);
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('sub_total', 15, 2)->default(0);
            $table->decimal('ppn_percent', 5, 2)->default(11);
            $table->decimal('ppn_amount', 15, 2)->default(0);
            $table->decimal('other_cost', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->string('po_status', 30)->nullable();
            $table->text('revision_reason')->nullable();
            $table->string('revised_by', 255)->nullable();
            $table->dateTime('revised_at')->nullable();

            $table->index(['purchase_order_document_id', 'revision_no'], 'po_doc_revisions_doc_rev_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_order_document_revisions');

        Schema::table('purchase_order_documents', function (Blueprint $table) {
            $table->dropColumn(['revision_no', 'po_status', 'processed_by', 'processed_at']);
        });
    }
}
