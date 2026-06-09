<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPoFieldsToPurchaseRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('po_number', 100)->nullable()->after('finance_status');
            $table->string('po_created_by', 255)->nullable()->after('po_number');
            $table->dateTime('po_created_at')->nullable()->after('po_created_by');
        });
    }

    public function down()
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn(['po_number', 'po_created_by', 'po_created_at']);
        });
    }
}
