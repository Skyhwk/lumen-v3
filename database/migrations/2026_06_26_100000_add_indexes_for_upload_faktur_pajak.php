<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesForUploadFakturPajak extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->index(['is_active', 'is_emailed', 'created_at'], 'idx_invoice_upload_faktur_filter');
            $table->index('file_faktur', 'idx_invoice_file_faktur');
            $table->index('no_order', 'idx_invoice_no_order');
            $table->index('no_invoice', 'idx_invoice_no_invoice');
        });

        Schema::table('order_header', function (Blueprint $table) {
            $table->index(['no_order', 'is_active'], 'idx_order_header_no_order_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->dropIndex('idx_invoice_upload_faktur_filter');
            $table->dropIndex('idx_invoice_file_faktur');
            $table->dropIndex('idx_invoice_no_order');
            $table->dropIndex('idx_invoice_no_invoice');
        });

        Schema::table('order_header', function (Blueprint $table) {
            $table->dropIndex('idx_order_header_no_order_active');
        });
    }
}