<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsAirHeaderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lhps_air_header', function (Blueprint $table) {
            $table->id(); // bigint AUTO_INCREMENT
            $table->string('no_order', 15)->nullable();
            $table->string('no_lhp', 15)->nullable();
            $table->string('no_sampel', 15)->nullable();
            $table->string('no_quotation', 70)->nullable();
            
            // Informasi Sampling & Pelanggan
            $table->string('status_sampling', 10)->nullable();
            $table->date('tanggal_terima')->nullable();
            $table->json('parameter_uji')->nullable();
            $table->text('nama_pelanggan')->nullable();
            $table->text('alamat_sampling')->nullable();
            $table->string('sub_kategori', 100)->nullable();
            $table->string('deskripsi_titik', 255)->nullable();
            $table->text('methode_sampling')->nullable();
            $table->date('tanggal_sampling')->nullable();
            $table->string('periode_analisa', 100)->nullable();
            
            // Regulasi & Hasil Lapangan
            $table->json('regulasi')->nullable();
            $table->json('regulasi_custom')->nullable();
            $table->json('keterangan')->nullable();
            $table->string('suhu_air', 10)->nullable();
            $table->string('suhu_udara', 5)->nullable();
            $table->string('ph', 10)->nullable();
            $table->string('dhl', 10)->nullable();
            $table->string('do', 10)->nullable();
            $table->string('warna', 15)->nullable();
            $table->string('bau', 15)->nullable();
            $table->string('titik_koordinat', 100)->nullable();
            
            // Laporan & Karyawan
            $table->json('header_table')->nullable();
            $table->string('nama_karyawan', 70)->nullable();
            $table->string('jabatan_karyawan', 70)->nullable();
            $table->string('file_qr', 50)->nullable();
            $table->string('file_lhp', 150)->nullable();
            $table->date('tanggal_lhp')->nullable();
            
            // Workflow Status & Audit Trails
            $table->integer('is_active')->default(1);
            $table->string('created_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->timestamp('updated_at')->nullable();
            
            $table->integer('is_approve')->default(0);
            $table->string('approved_by', 150)->nullable();
            $table->timestamp('approved_at')->nullable();
            
            $table->string('deleted_by', 150)->nullable();
            $table->timestamp('deleted_at')->nullable();
            
            $table->integer('is_generated')->default(0);
            $table->string('generated_by', 150)->nullable();
            $table->timestamp('generated_at')->nullable();
            
            $table->integer('is_emailed')->default(0);
            $table->string('emailed_by', 150)->nullable();
            $table->timestamp('emailed_at')->nullable();
            
            $table->integer('is_reject')->default(0);
            $table->string('rejected_by', 150)->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            // Parameter Kontrol Tambahan
            $table->integer('id_token')->nullable();
            $table->date('expired')->nullable();
            $table->tinyInteger('is_reject_customer')->default(0);
            $table->text('reason_reject_customer')->nullable();
            $table->integer('count_reject')->default(0);
            $table->integer('count_print')->default(0);
            $table->integer('is_printed')->default(0);
            $table->integer('is_revisi')->default(0);
            $table->integer('count_revisi')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_air_header');
    }
}
