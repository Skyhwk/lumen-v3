<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('surat_peringatan_disipliner')) {
            return;
        }

        Schema::create('surat_peringatan_disipliner', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('karyawan_id');
            $table->string('jenis_peringatan', 10);
            $table->string('nomor_surat', 100)->unique();
            /** Tahun penomoran surat (reset urutan per tahun, dipakai generate nomor ISL/SPx/YYxxxx). */
            $table->unsignedSmallInteger('tahun_nomor');
            /** Urutan increment nomor surat dalam tahun_nomor + jenis SP. */
            $table->unsignedInteger('urutan_nomor');
            $table->date('tanggal_terbit');
            $table->date('tanggal_kejadian')->nullable();
            $table->string('jenis_pelanggaran', 150)->nullable();
            $table->longText('uraian_pelanggaran')->nullable()->comment('HTML dari TinyMCE');
            $table->longText('catatan_hrd')->nullable();
            $table->text('bentuk_sanksi')->nullable();
            $table->unsignedTinyInteger('masa_berlaku_bulan')->nullable();
            $table->date('tanggal_berlaku_mulai')->nullable();
            $table->date('tanggal_berlaku_selesai')->nullable();
            $table->unsignedBigInteger('id_surat_sebelumnya')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('nama_berkas', 255)->nullable();
            $table->text('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('created_by', 150)->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->string('published_by', 150)->nullable()->comment('Petugas HR saat surat diterbitkan');
            $table->string('nama_penandatangan', 150)->nullable();
            $table->string('jabatan_penandatangan', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['karyawan_id', 'jenis_peringatan', 'status'], 'spd_karyawan_jenis_status_idx');
            $table->index(['tanggal_terbit', 'status'], 'spd_tgl_terbit_status_idx');
            $table->index('id_surat_sebelumnya', 'spd_surat_sebelumnya_idx');
            $table->index(['jenis_peringatan', 'tahun_nomor', 'urutan_nomor'], 'spd_nomor_tahun_urutan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_peringatan_disipliner');
    }
};
