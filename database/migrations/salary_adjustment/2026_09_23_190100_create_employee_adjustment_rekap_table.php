<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_adjustment_rekap')) {
            return;
        }

        Schema::create('employee_adjustment_rekap', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->unique();
            $table->string('request_type', 50);
            $table->string('no_document', 50);
            $table->unsignedBigInteger('employee_id');
            $table->string('employee_nama', 255);
            $table->string('employee_nik', 50)->nullable();
            $table->string('jabatan_lama', 100)->nullable();
            $table->string('jabatan_baru', 100)->nullable();
            $table->unsignedBigInteger('manager_pengaju_id')->nullable();
            $table->string('manager_pengaju_nama', 255)->nullable();
            $table->unsignedBigInteger('manager_penerima_id')->nullable();
            $table->string('manager_penerima_nama', 255)->nullable();
            $table->string('status_karyawan_lama', 50)->nullable();
            $table->string('status_karyawan_baru', 50)->nullable();
            $table->decimal('gaji_lama', 15, 2)->nullable();
            $table->decimal('gaji_baru', 15, 2)->nullable();
            $table->decimal('tunjangan_lama', 15, 2)->nullable();
            $table->decimal('tunjangan_baru', 15, 2)->nullable();
            $table->date('tanggal_efektif')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->date('tanggal_berakhir_kerja')->nullable();
            $table->date('tgl_berakhir_kontrak_lama')->nullable();
            $table->date('tgl_berakhir_kontrak_baru')->nullable();
            $table->text('kpi_summary')->nullable();
            $table->decimal('kpi_score_avg', 5, 2)->nullable();
            $table->timestamp('approved_bapak_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->json('payload_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('request_type');
            $table->index('employee_id');
            $table->index('applied_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_adjustment_rekap');
    }
};
