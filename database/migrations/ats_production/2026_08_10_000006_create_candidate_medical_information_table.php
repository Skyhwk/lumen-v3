<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCandidateMedicalInformationTable extends Migration
{
    /**
     * Run the migrations for candidate_medical_informations table.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('candidate_medical_informations')) {
            Schema::create('candidate_medical_informations', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('new_recruitment_id')->nullable();
                $table->foreign('new_recruitment_id')
                    ->references('id')
                    ->on('new_recruitment')
                    ->nullOnDelete();

                $table->unsignedBigInteger('candidate_profile_id')->nullable();
                $table->foreign('candidate_profile_id')
                    ->references('id')
                    ->on('candidate_profiles')
                    ->nullOnDelete();

                // Data Fisik
                $table->decimal('tinggi_badan', 5, 1)->nullable();
                $table->decimal('berat_badan', 5, 1)->nullable();
                $table->string('mata', 100)->nullable();
                $table->string('golongan_darah', 5)->nullable();

                // Riwayat Kesehatan
                $table->text('penyakit_bawaan_lahir')->nullable();
                $table->text('penyakit_kronis')->nullable();
                $table->text('riwayat_kecelakaan')->nullable();

                // Audit
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->timestamps();
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
        Schema::dropIfExists('candidate_medical_informations');
    }
}
