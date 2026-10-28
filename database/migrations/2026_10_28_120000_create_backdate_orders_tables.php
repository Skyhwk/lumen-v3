<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBackdateOrdersTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('backdate_orders')) {
            Schema::create('backdate_orders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('no_order', 100)->index();
                $table->string('no_quotation', 100)->nullable()->index();
                $table->string('nama_perusahaan', 255)->nullable();
                $table->enum('status', ['backdated', 'restored'])->default('backdated');
                $table->text('catatan')->nullable();
                $table->string('created_by', 100)->nullable();
                $table->string('restored_by', 100)->nullable();
                $table->timestamp('restored_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('backdate_order_details')) {
            Schema::create('backdate_order_details', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('backdate_order_id')->index();
                $table->string('category', 50)->comment('quotation, jadwal, sampling, analisa, drafting, lhp, foto');
                $table->string('table_name', 100);
                $table->unsignedBigInteger('record_id')->nullable();
                $table->string('field_name', 100);
                $table->longText('original_value')->nullable();
                $table->longText('backdate_value')->nullable();
                $table->string('backup_file_path', 255)->nullable();
                $table->timestamps();

                $table->foreign('backdate_order_id')
                    ->references('id')
                    ->on('backdate_orders')
                    ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('backdate_order_details');
        Schema::dropIfExists('backdate_orders');
    }
}

