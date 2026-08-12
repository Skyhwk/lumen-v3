<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCandidateDataOffersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('candidate_data_offers')) {
            Schema::create('candidate_data_offers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('new_recruitment_id')->nullable();
                $table->decimal('gaji_pokok', 15, 2)->nullable();
                $table->decimal('potongan_bpjs_kes', 15, 2)->nullable();
                $table->decimal('potongan_bpjs_tk', 15, 2)->nullable();
                $table->decimal('pot_pph21', 15, 2)->nullable();
                $table->date('tanggal_mulai_kerja')->nullable();
                $table->decimal('pencadangan_upah', 15, 2)->nullable();
                $table->string('created_by', 150)->nullable();
                $table->string('updated_by', 150)->nullable();
                $table->timestamps();

                $table->foreign('new_recruitment_id')
                    ->references('id')
                    ->on('new_recruitment')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('candidate_data_offers');
    }
}
