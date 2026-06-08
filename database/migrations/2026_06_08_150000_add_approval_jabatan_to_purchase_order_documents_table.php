<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalJabatanToPurchaseOrderDocumentsTable extends Migration
{
    public function up()
    {
        Schema::table('purchase_order_documents', function (Blueprint $table) {
            $table->string('approval_jabatan', 255)->nullable()->after('approval_name');
        });
    }

    public function down()
    {
        Schema::table('purchase_order_documents', function (Blueprint $table) {
            $table->dropColumn('approval_jabatan');
        });
    }
}
