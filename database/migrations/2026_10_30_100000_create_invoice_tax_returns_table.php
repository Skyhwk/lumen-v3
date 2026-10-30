<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceTaxReturnsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('invoice_tax_returns')) return;
        Schema::create('invoice_tax_returns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('no_invoice', 100)->index();
            $table->string('faktur_pajak', 150);
            $table->string('nama_perusahaan', 255)->nullable();
            $table->date('return_date');
            $table->decimal('dpp', 20, 2);
            $table->decimal('ppn', 20, 2);
            $table->decimal('amount', 20, 2);
            $table->string('amount_basis', 30)->default('dpp_plus_ppn');
            $table->text('reason');
            $table->string('status', 30)->default('draft')->index();
            $table->string('request_key', 100)->unique();
            $table->longText('invoice_snapshot');
            $table->longText('meta_history');
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_name', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoice_tax_returns');
    }
}
