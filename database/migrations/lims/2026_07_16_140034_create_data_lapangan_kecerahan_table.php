<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganKecerahanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_kecerahan', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 50)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->string('parameter', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->text('informasi_tambahan')->nullable();
            
            // Koordinat Geografis
            $table->string('latitude', 50)->nullable();
            $table->string('longitude', 50)->nullable();
            $table->string('titik_koordinat', 100)->nullable();
            
            // Data Secchi Disk & Kecerahan Air
            $table->string('kedalaman_air', 20)->nullable();
            $table->string('kedalaman_secchi_1', 20)->nullable();
            $table->string('kedalaman_secchi_2', 20)->nullable();
            $table->string('kedalaman_secchi_3', 20)->nullable();
            $table->string('nilai_kecerahan', 20)->nullable()->comment('ws');
            $table->string('waktu_pengambilan', 100)->nullable();
            $table->text('foto_aktifitas_sampling')->nullable();
            
            // Workflow Status & Audit Trails
            $table->integer('permission')->default(0);
            $table->string('created_by', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('approved_by', 100)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('blocked_by', 100)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('is_approve')->default(0);
            $table->integer('is_blocked')->default(0);
            $table->string('rejected_by', 100)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->integer('is_rejected')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_kecerahan');
    }
}
