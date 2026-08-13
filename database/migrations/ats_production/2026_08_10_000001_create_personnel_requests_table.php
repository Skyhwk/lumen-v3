<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersonnelRequestsTable extends Migration
{
    /**
     * Run the migrations for personnel_requests table.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('personnel_requests')) {
            Schema::create('personnel_requests', function (Blueprint $table) {
                $table->id();
                $table->string('no_request', 255)->unique();
                $table->enum('request_type', ['replacement', 'new_headcount']);

                // Information for replacement
                $table->string('karyawan_lama_nama', 255)->nullable();
                $table->string('karyawan_lama_nik', 255)->nullable();
                $table->string('alasan_replacement', 255)->nullable();
                $table->string('alasan_replacement_lainnya', 255)->nullable();

                // General Request Details
                $table->string('divisi', 255)->nullable();
                $table->string('posisi', 255)->nullable();
                $table->integer('jumlah_personal')->nullable();
                $table->integer('lokasi_penempatan_cabang')->nullable();
                $table->string('grade_master_karyawan', 255)->nullable();
                $table->text('alasan_kebutuhan')->nullable();
                $table->text('job_description')->nullable();

                // Qualifications
                $table->string('pendidikan', 255)->nullable();
                $table->string('pengalaman_kerja', 255)->nullable();
                $table->integer('usia_maksimum')->nullable();
                $table->enum('gender', ['no_preference', 'laki-laki', 'perempuan'])->nullable();
                $table->text('skill_wajib')->nullable();
                $table->text('sertifikasi')->nullable();
                $table->date('tanggal_dibutuhkan')->nullable();
                $table->enum('prioritas', ['low', 'medium', 'high', 'urgent'])->nullable();
                $table->decimal('max_salary', 15, 2)->nullable();

                // Publish & Approvals
                $table->boolean('is_publish')->default(false)->nullable();
                $table->string('published_by', 255)->nullable();
                $table->timestamp('published_at')->nullable();

                $table->boolean('is_approve')->default(false)->nullable();
                $table->string('approved_by', 255)->nullable();
                $table->timestamp('approved_at')->nullable();

                $table->boolean('is_reject')->default(false)->nullable();
                $table->string('rejected_by', 255)->nullable();
                $table->timestamp('rejected_at')->nullable();

                $table->boolean('is_active')->default(true)->nullable();
                $table->string('cancled_by', 255)->nullable();
                $table->timestamp('cancled_at')->nullable();

                $table->string('created_by', 255);
                $table->string('updated_by', 255)->nullable();
                $table->timestamps();

                $table->string('divisi_alias', 100)->nullable();
                $table->integer('minimum_matching')->nullable();
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
        Schema::dropIfExists('personnel_requests');
    }
}
