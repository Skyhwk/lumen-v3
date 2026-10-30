<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('berita_acara_kejadian_disipliner')) {
            return;
        }

        Schema::create('berita_acara_kejadian_disipliner', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('karyawan_id');
            $table->unsignedInteger('penandatangan_karyawan_id');
            $table->string('penandatangan_nama', 150)->nullable();
            $table->string('penandatangan_tempat_lahir', 150)->nullable();
            $table->string('penandatangan_tanggal_lahir', 30)->nullable();
            $table->string('penandatangan_nomor_hp', 30)->nullable();
            $table->text('penandatangan_alamat')->nullable();
            $table->string('penandatangan_nik', 50)->nullable();
            $table->string('penandatangan_jabatan', 150)->nullable();
            $table->string('penandatangan_divisi', 150)->nullable();
            $table->string('penandatangan_penempatan', 150)->nullable();
            $table->string('nomor_surat', 100)->unique();
            $table->unsignedSmallInteger('tahun_nomor');
            $table->unsignedInteger('urutan_nomor')->default(0);
            $table->date('tanggal_kejadian');
            $table->string('waktu_kejadian', 10)->nullable()->comment('Format HH:mm');
            $table->string('lokasi_kejadian', 255)->nullable();
            $table->string('telah_terjadi_kejadian', 500)->nullable();
            $table->text('dampak_aset_perusahaan')->nullable();
            $table->string('lampiran', 100)->nullable();
            $table->string('perihal', 200)->nullable();
            $table->longText('uraian_kejadian')->nullable()->comment('HTML dari TinyMCE');
            $table->longText('catatan')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('nama_berkas', 255)->nullable();
            $table->text('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_qr', 255)->nullable();
            $table->string('created_by', 150)->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->string('published_by', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['karyawan_id', 'status'], 'bak_karyawan_status_idx');
            $table->index(['tanggal_kejadian', 'status'], 'bak_tgl_kejadian_status_idx');
            $table->index('penandatangan_karyawan_id', 'bak_penandatangan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berita_acara_kejadian_disipliner');
    }
};
