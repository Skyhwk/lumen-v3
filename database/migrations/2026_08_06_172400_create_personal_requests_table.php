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
    Schema::create('personnel_requests', function (Blueprint $table) {
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
        $table->integer('lokasi_penempatan_cabang')->nullable();
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
        $table->string('divisi_alias')->nullable();
        
        // Published
        // Saran: tambahkan default(false) agar datanya konsisten saat pertama kali diinsert
        $table->boolean('is_publish')->default(false)->nullable(); 
        $table->string('published_by')->nullable();
        $table->timestamp('published_at')->nullable();
        
        // Approved
        $table->boolean('is_approve')->default(false)->nullable();
        $table->string('approved_by')->nullable();
        $table->timestamp('approved_at')->nullable();
        
        // Rejected
        $table->boolean('is_reject')->default(false)->nullable();
        $table->string('rejected_by')->nullable();
        $table->timestamp('rejected_at')->nullable();
        
        // cancle
        $table->boolean('is_active')->default(true)->nullable();
        $table->string('cancled_by')->nullable();
        $table->timestamp('cancled_at')->nullable();
        
        $table->string('updated_by')->nullable();
        $table->string('created_by')->nullabel();
        // Otomatis membuat kolom created_at dan updated_at
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
        Schema::dropIfExists('personnel_requests');
    }
}
