<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('history_perubahan_karyawan')) {
            return;
        }

        Schema::create('history_perubahan_karyawan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('id_karyawan');
            $table->string('nama_lengkap', 255)->nullable();

            // NIK Karyawan
            $table->string('nik_karyawan_lama', 100)->nullable();
            $table->string('nik_karyawan_baru', 100)->nullable();

            // Status Karyawan
            $table->string('status_karyawan_lama', 100)->nullable();
            $table->string('status_karyawan_baru', 100)->nullable();

            // Grade & Kategori Grade
            $table->string('grade_lama', 100)->nullable();
            $table->string('grade_baru', 100)->nullable();
            $table->string('kategori_grade_lama', 100)->nullable();
            $table->string('kategori_grade_baru', 100)->nullable();

            // Jabatan & ID Jabatan
            $table->unsignedInteger('id_jabatan_lama')->nullable();
            $table->unsignedInteger('id_jabatan_baru')->nullable();
            $table->string('jabatan_lama', 255)->nullable();
            $table->string('jabatan_baru', 255)->nullable();

            // Department & ID Department
            $table->unsignedInteger('id_department_lama')->nullable();
            $table->unsignedInteger('id_department_baru')->nullable();
            $table->string('department_lama', 255)->nullable();
            $table->string('department_baru', 255)->nullable();

            // Cabang
            $table->unsignedInteger('id_cabang_lama')->nullable();
            $table->unsignedInteger('id_cabang_baru')->nullable();

            // Privilege Cabang & Atasan Langsung
            $table->text('privilage_cabang_lama')->nullable();
            $table->text('privilage_cabang_baru')->nullable();
            $table->text('atasan_langsung_lama')->nullable();
            $table->text('atasan_langsung_baru')->nullable();

            // Ringkasan field yang berubah & snapshot JSON
            $table->json('changed_fields')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();

            $table->string('updated_by', 150)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('id_karyawan', 'idx_hpk_id_karyawan');
            $table->index('created_at', 'idx_hpk_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('history_perubahan_karyawan');
    }
};
