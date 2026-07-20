<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsLingHeaderTable extends Migration
{
    public function up(): void
    {
        Schema::create('lhps_ling_header', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('no_order', 15)->nullable();
            $table->string('no_lhp', 15)->nullable();
            $table->text('no_sampel')->nullable();
            $table->string('no_qt', 70)->nullable();
            $table->string('status_sampling', 50)->nullable();
            $table->json('parameter_uji')->nullable();
            $table->text('nama_pelanggan')->nullable();
            $table->text('alamat_sampling')->nullable();
            $table->string('sub_kategori', 100)->nullable();
            $table->integer('id_kategori_2')->nullable();
            $table->integer('id_kategori_3')->nullable();
            $table->text('deskripsi_titik')->nullable();
            $table->string('methode_sampling', 100)->nullable();
            $table->string('tanggal_sampling', 100)->nullable(); // varchar di SQL asli
            $table->date('tanggal_terima')->nullable();
            $table->string('periode_analisa', 100)->nullable();
            $table->date('tanggal_sampling_awal')->nullable();
            $table->date('tanggal_sampling_akhir')->nullable();
            $table->date('tanggal_analisa_awal')->nullable();
            $table->date('tanggal_analisa_akhir')->nullable();
            $table->json('regulasi')->nullable();
            $table->json('regulasi_custom')->nullable();
            $table->json('keterangan')->nullable();
            $table->string('suhu', 10)->nullable();
            $table->string('cuaca', 70)->nullable();
            $table->string('arah_angin', 70)->nullable();
            $table->string('kelembapan', 10)->nullable();
            $table->string('kec_angin', 10)->nullable();
            $table->string('tekanan_udara', 15)->nullable();
            $table->string('waktu_pengukuran', 10)->nullable();
            $table->string('titik_koordinat', 100)->nullable();
            $table->json('header_table')->nullable();
            $table->string('nama_karyawan', 70)->nullable();
            $table->string('jabatan_karyawan', 70)->nullable();
            $table->string('file_qr', 50)->nullable();
            $table->string('file_lhp', 150)->nullable();
            $table->date('tanggal_lhp')->nullable();
            $table->integer('is_active')->default(1);
            $table->string('created_by', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->tinyInteger('is_approved')->default(0);
            $table->string('approved_by', 100)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_by', 70)->nullable();
            $table->string('deleted_by', 100)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->tinyInteger('is_generated')->default(0);
            $table->string('generated_by', 100)->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->tinyInteger('is_emailed')->default(0);
            $table->string('emailed_by', 100)->nullable();
            $table->dateTime('emailed_at')->nullable();
            $table->integer('id_token')->nullable();
            $table->date('expired')->nullable();
            $table->integer('is_revisi')->default(0);
            $table->integer('count_revisi')->default(0);
            $table->integer('is_printed')->default(0);
            $table->integer('count_print')->default(0);
            $table->tinyInteger('is_many_sampel')->default(0);
            $table->json('metode_sampling')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_ling_header');
    }
}
