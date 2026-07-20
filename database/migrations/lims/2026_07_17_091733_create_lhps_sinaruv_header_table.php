<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsSinaruvHeaderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lhps_sinaruv_header', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_order', 15)->nullable();
            $table->string('no_lhp', 15)->nullable();
            $table->text('no_sampel')->nullable();
            $table->string('no_qt', 70)->nullable();
            
            // Informasi Sampling & Pelanggan
            $table->string('status_sampling', 100)->nullable();
            $table->text('nama_pelanggan')->nullable();
            $table->text('alamat_sampling')->nullable();
            $table->json('parameter_uji')->nullable();
            $table->integer('id_kategori_2')->nullable();
            $table->integer('id_kategori_3')->nullable();
            $table->string('sub_kategori', 70)->nullable();
            $table->json('metode_sampling')->nullable();
            
            // Waktu & Identitas Sampel
            $table->text('tanggal_sampling')->nullable();
            $table->date('tanggal_terima')->nullable();
            $table->string('tanggal_sampling_text', 70)->nullable();
            $table->string('periode_analisa', 100)->nullable();
            $table->string('jenis_sampel', 100)->nullable();
            
            // Regulasi & Keterangan
            $table->integer('id_regulasi')->nullable();
            $table->json('regulasi')->nullable();
            $table->json('regulasi_custom')->nullable();
            $table->text('keterangan')->nullable();
            
            // Karyawan & Berkas
            $table->string('nama_karyawan', 150)->nullable();
            $table->string('jabatan_karyawan', 100)->nullable();
            $table->string('file_qr', 150)->nullable();
            $table->string('file_lhp', 150)->nullable();
            $table->date('tanggal_lhp')->nullable();
            
            // Workflow Status & Audit Trails
            $table->integer('is_active')->default(1);
            $table->string('created_by', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamp('updated_at')->nullable();
            
            $table->integer('is_approve')->default(0);
            $table->string('approved_by', 100)->nullable();
            $table->timestamp('approved_at')->nullable();
            
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            $table->string('deleted_by', 100)->nullable();
            $table->timestamp('deleted_at')->nullable();
            
            $table->tinyInteger('is_generated')->default(0);
            $table->string('generated_by', 100)->nullable();
            $table->timestamp('generated_at')->nullable();
            
            $table->tinyInteger('is_emailed')->default(0);
            $table->string('emailed_by', 100)->nullable();
            $table->timestamp('emailed_at')->nullable();
            
            // Log & Parameter Kontrol
            $table->integer('id_token')->nullable();
            $table->date('expired')->nullable();
            $table->integer('count_print')->default(0);
            $table->integer('is_revisi')->default(0);
            $table->integer('count_revisi')->default(0);
            $table->integer('is_printed')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_sinaruv_header');
    }
}
