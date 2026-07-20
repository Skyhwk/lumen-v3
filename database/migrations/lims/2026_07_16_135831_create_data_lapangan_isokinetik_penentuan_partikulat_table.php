<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganIsokinetikPenentuanPartikulatTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_isokinetik_penentuan_partikulat', function (Blueprint $table) {
            $table->increments('id'); // int AUTO_INCREMENT
            $table->integer('id_lapangan')->nullable();
            $table->string('no_sampel', 30)->nullable();
            $table->json('no_sampel_lama')->nullable();
            
            // Parameter Lintas Partikulat
            $table->string('diameter', 15)->nullable();
            $table->string('titik_lintas_partikulat', 15)->nullable();
            $table->string('data_Y', 20)->nullable();
            $table->string('Delta_H', 20)->nullable();
            $table->string('dn_req', 20)->nullable();
            $table->string('k_iso', 50)->nullable();
            $table->string('delta_H_req', 50)->nullable();
            $table->string('waktu', 20)->nullable();
            $table->string('dn_actual', 20)->nullable();
            $table->integer('impinger1')->nullable();
            $table->integer('impinger2')->nullable();
            $table->integer('impinger3')->nullable();
            $table->integer('impinger4')->nullable();
            $table->string('Vs', 50)->nullable();
            $table->integer('dgmAwal')->nullable();
            
            // Data Mentah / Teks Observasi
            $table->text('DGM')->nullable();
            $table->text('dP')->nullable();
            $table->text('PaPs')->nullable();
            $table->text('dH')->nullable();
            $table->text('Stack')->nullable();
            $table->text('Meter')->nullable();
            $table->text('Vp')->nullable();
            $table->text('Filter')->nullable();
            $table->text('Oven')->nullable();
            $table->text('exit_impinger')->nullable();
            $table->text('Probe')->nullable();
            
            // Data Molekul Partikulat
            $table->string('CO2', 20)->nullable();
            $table->string('CO', 20)->nullable();
            $table->string('NOx', 20)->nullable();
            $table->string('SO2', 20)->nullable();
            $table->string('o2_mole', 20)->nullable();
            $table->string('co2_mole', 20)->nullable();
            $table->string('co_mole', 20)->nullable();
            $table->string('n2_mole', 20)->nullable();
            $table->string('md', 20)->nullable();
            $table->string('ms', 20)->nullable();
            $table->string('nco2', 20)->nullable();
            $table->string('combustion', 20)->nullable();
            $table->string('Total_time', 20)->nullable();
            
            // Kolom Not Null tanpa default
            $table->string('pbar', 20);
            $table->string('rataselisihdgm', 20);
            $table->json('sebelumpengujian');
            $table->json('sesudahpengujian');
            $table->string('temperatur_stack', 20);
            $table->json('data_total_vs');
            $table->json('delta_vm');
            
            // Dokumentasi
            $table->string('foto_lokasi_sampel', 30)->nullable();
            $table->string('foto_kondisi_sampel', 30)->nullable();
            $table->string('foto_lain', 30)->nullable();
            
            // Workflow Status & Audit Trails
            $table->integer('permission')->nullable();
            $table->integer('is_active')->default(1);
            $table->integer('is_blocked')->default(0);
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('blocked_by', 70)->default('0');
            $table->timestamp('blocked_at')->nullable();
            $table->integer('is_approve')->default(0);
            $table->string('approved_by', 70)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Indexing
            $table->index('no_sampel', 'idx_dliskpp_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_isokinetik_penentuan_partikulat');
    }
}
