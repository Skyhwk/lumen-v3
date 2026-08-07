<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersonalRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('personal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('no_request')->unique();
            $table->enum('request_type', ['replacement', 'new_headcount']);
            
            // Kolom khusus replacement
            $table->string('karyawan_lama_nama')->nullable();
            $table->string('karyawan_lama_nik')->nullable();
            $table->string('alasan_replacement')->nullable(); // resign, promosi, mutasi, pensiun, phk, meninggal, lainnya
            $table->string('alasan_replacement_lainnya')->nullable(); // freetext
            
            // Informasi Pekerjaan
            $table->string('divisi')->nullable();
            $table->string('posisi')->nullable();
            $table->integer('jumlah_personal')->nullable();
            $table->string('lokasi_penempatan_cabang')->nullable();
            $table->string('grade_master_karyawan')->nullable();
            $table->text('alasan_kebutuhan')->nullable();
            $table->text('job_description')->nullable();
            
            // Kualifikasi Kandidat
            $table->string('pendidikan')->nullable(); // SMA keatas, dll
            $table->string('pengalaman_kerja')->nullable(); // fresh graduate, 1-5 thn, dll
            $table->integer('usia_maksimum')->nullable();
            $table->enum('gender', ['no_preference', 'laki-laki', 'perempuan'])->nullable();
            $table->text('skill_wajib')->nullable();
            $table->text('sertifikasi')->nullable();
            
            // Detail Waktu & Prioritas
            $table->date('tanggal_dibutuhkan')->nullable();
            $table->enum('prioritas', ['low', 'medium', 'high', 'urgent'])->nullable();
            $table->decimal('max_salary', 15, 2)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('personal_requests');
    }
}
