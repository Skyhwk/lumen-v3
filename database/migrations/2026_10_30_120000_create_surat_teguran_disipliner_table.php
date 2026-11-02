<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('surat_teguran_disipliner')) {
            return;
        }

        Schema::create('surat_teguran_disipliner', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('karyawan_id');
            $table->string('nomor_surat', 100)->unique();
            $table->unsignedSmallInteger('tahun_nomor')->nullable();
            $table->unsignedInteger('urutan_nomor')->nullable();
            $table->string('perihal', 255);
            /** Legacy/internal; diisi otomatis saat create, tidak dipakai di form. */
            $table->date('tanggal_terbit');
            $table->date('tanggal_kejadian')->nullable();
            $table->longText('uraian_pelanggaran')->comment('HTML daftar poin pelanggaran');
            $table->string('status', 20)->default('draft');
            $table->string('file_qr', 100)->nullable();
            $table->string('nama_berkas', 255)->nullable();
            $table->text('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('created_by', 150)->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->string('published_by', 150)->nullable();
            $table->string('nama_penandatangan', 150)->nullable();
            $table->string('jabatan_penandatangan', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['karyawan_id', 'status'], 'std_karyawan_status_idx');
            $table->index(['tanggal_terbit', 'status'], 'std_tgl_terbit_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_teguran_disipliner');
    }
};
