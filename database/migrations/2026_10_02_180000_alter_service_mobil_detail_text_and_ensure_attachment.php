<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spare part & biaya bisa berisi rincian HTML (TinyMCE).
 * Tabel service_mobil_attachment sudah ada dari migration awal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_mobil_detail')) {
            try {
                DB::statement('ALTER TABLE service_mobil_detail MODIFY spare_part TEXT NULL');
                DB::statement('ALTER TABLE service_mobil_detail MODIFY biaya TEXT NULL');
            } catch (\Throwable $e) {
                // ignore if already text / driver difference
            }
        }

        if (!Schema::hasTable('service_mobil_attachment')) {
            Schema::create('service_mobil_attachment', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('service_mobil_id');
                $table->string('jenis_lampiran', 50)->default('penyelesaian');
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
        if (Schema::hasTable('service_mobil_detail')) {
            try {
                DB::statement('ALTER TABLE service_mobil_detail MODIFY spare_part VARCHAR(255) NULL');
                DB::statement('ALTER TABLE service_mobil_detail MODIFY biaya DECIMAL(15,2) NULL');
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
};
