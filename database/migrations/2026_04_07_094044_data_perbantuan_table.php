<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DataPerbantuanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('data_perbantuan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama_pelanggan', 250)->nullable();
            $table->string('id_pelanggan', 100)->nullable();
            $table->string('sales_id', 10)->nullable();
            $table->string('karyawan_id', 10)->nullable();
            $table->string('nama_pic', 200)->nullable();
            $table->string('no_perusahaan', 20)->nullable();
            $table->string('no_pic', 20)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('type_keterangan',250)->nullable();
            $table->boolean('is_checked')->default(false);
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('updated_by', 70)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->string('deleted_by', 70)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('data_perbantuan');
    }
}
