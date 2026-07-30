<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBiayaOperasionalReceiptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('biaya_operasional_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bo_id');
            $table->string('file_name');
            $table->string('original_name')->nullable();
            $table->string('created_by', 150)->nullable();
            $table->dateTime('created_at')->nullable();

            $table->foreign('bo_id')->references('id')->on('biaya_operasional')->onDelete('cascade');
            $table->index('bo_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('biaya_operasional_receipts');
    }
}
