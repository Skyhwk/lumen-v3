<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsEmisiIsokinetikHeaderHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('lhps_emisi_isokinetik_header_history', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_order', 15)->nullable();
            $table->string('no_lhp', 15)->nullable();
            $table->string('no_sampel', 50)->nullable();
            $table->string('no_quotation', 70)->nullable();
            $table->string('nama_pelanggan', 200)->nullable();
            $table->string('konsultan', 150)->nullable();
            $table->string('deskripsi_titik', 150)->nullable();
            $table->string('nama_pic', 100)->nullable();
            $table->string('email_pic', 70)->nullable();
            $table->string('metode_sampling', 70)->nullable();
            $table->string('jabatan_pic', 100)->nullable();
            $table->string('no_pic', 15)->nullable();
            $table->text('alamat_sampling')->nullable();
            $table->json('parameter_uji')->nullable();
            $table->string('type_sampling', 50)->nullable();
            $table->string('fpps', 50)->nullable();
            $table->string('kategori', 50)->nullable();
            $table->integer('id_kategori_2')->nullable();
            $table->integer('id_kategori_3')->nullable();
            $table->string('sub_kategori', 100)->nullable();
            $table->date('tanggal_terima')->nullable();
            $table->date('tanggal_tugas')->nullable();
            $table->date('tanggal_lhp')->nullable();
            $table->string('periode_analisa', 100)->nullable();
            $table->json('regulasi')->nullable();
            $table->json('regulasi_custom')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('titik_koordinat', 150)->nullable();
            $table->string('velocity', 50)->nullable();
            $table->string('file_qr', 70)->nullable();
            $table->string('file_lhp', 150)->nullable();
            $table->string('nama_karyawan', 150)->nullable();
            $table->string('jabatan_karyawan', 150)->nullable();
            $table->string('created_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('update_by')->nullable();
            $table->timestamp('update_at')->nullable();
            $table->integer('is_approve')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by', 150)->nullable();
            $table->integer('is_reject')->default(0);
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_by', 70)->nullable();
            $table->integer('is_active')->default(1);
            $table->timestamp('delete_at')->nullable();
            $table->string('delete_by', 150)->nullable();
            $table->integer('is_generated')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->string('generated_by', 150)->nullable();
            $table->integer('is_emailed')->default(0);
            $table->timestamp('emailed_at')->nullable();
            $table->string('emailed_by', 150)->nullable();
            $table->integer('count_print')->nullable();
            $table->integer('count_reject')->nullable();
            $table->integer('is_revisi')->default(0);
            $table->integer('count_revisi')->nullable();
            $table->integer('is_printed')->default(0);
            $table->integer('id_token')->nullable();
            $table->timestamp('expired')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_emisi_isokinetik_header_history');
    }
}
