<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_mobil')) {
            Schema::create('service_mobil', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('daftar_mobil_id');

                // Snapshot identitas kendaraan (history tetap terbaca bila master berubah)
                $table->string('plat_mobil', 30);
                $table->string('merk_mobil', 100)->nullable();
                $table->string('tipe_mobil', 100)->nullable();

                $table->date('tanggal_mulai_rencana');
                $table->date('tanggal_selesai_rencana_awal');
                $table->date('tanggal_selesai_estimasi');
                $table->date('tanggal_mulai_aktual')->nullable();
                $table->date('tanggal_selesai_aktual')->nullable();

                // dijadwalkan | dalam_perbaikan | selesai | dibatalkan
                $table->string('status', 30)->default('dijadwalkan');
                $table->text('keluhan');
                $table->string('nama_bengkel', 150)->nullable();
                $table->text('catatan')->nullable();
                $table->unsignedInteger('kilometer')->nullable();
                $table->text('catatan_hasil')->nullable();
                $table->text('alasan_pembatalan')->nullable();

                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->string('started_by', 255)->nullable();
                $table->dateTime('started_at')->nullable();
                $table->string('completed_by', 255)->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->string('canceled_by', 255)->nullable();
                $table->dateTime('canceled_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->index('daftar_mobil_id');
                $table->index('status');
                $table->index('plat_mobil');
                $table->index(['tanggal_mulai_rencana', 'tanggal_selesai_estimasi'], 'service_mobil_periode_idx');
                $table->index(['status', 'is_active'], 'service_mobil_status_active_idx');
            });
        }

        if (!Schema::hasTable('service_mobil_detail')) {
            Schema::create('service_mobil_detail', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('service_mobil_id');
                $table->text('deskripsi_pekerjaan');
                $table->text('spare_part')->nullable();
                $table->text('biaya')->nullable();
                $table->unsignedSmallInteger('urutan')->default(1);
                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->index('service_mobil_id');
            });
        }

        if (!Schema::hasTable('service_mobil_log')) {
            Schema::create('service_mobil_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('service_mobil_id');
                // dibuat | jadwal_diubah | dimulai | diperpanjang | selesai | dibatalkan
                $table->string('jenis_tindakan', 50);
                $table->json('nilai_sebelum')->nullable();
                $table->json('nilai_sesudah')->nullable();
                $table->text('alasan')->nullable();
                $table->text('catatan')->nullable();
                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();

                $table->index('service_mobil_id');
                $table->index('jenis_tindakan');
            });
        }

        if (!Schema::hasTable('service_mobil_attachment')) {
            Schema::create('service_mobil_attachment', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('service_mobil_id');
                // perpanjangan | penyelesaian | lainnya
                $table->string('jenis_lampiran', 50)->default('lainnya');
                $table->string('nama_file', 255);
                $table->string('path_file', 500);
                $table->string('mime_type', 100)->nullable();
                $table->unsignedInteger('ukuran_byte')->nullable();
                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->index('service_mobil_id');
                $table->index('jenis_lampiran');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_mobil_attachment');
        Schema::dropIfExists('service_mobil_log');
        Schema::dropIfExists('service_mobil_detail');
        Schema::dropIfExists('service_mobil');
    }
};
