<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsEmisiHeaderHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('lhps_emisi_header_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('no_order', 15);
            $table->string('no_lhp', 15);
            $table->string('no_quotation', 70);
            $table->text('nama_pelanggan');
            $table->string('konsultan', 150)->nullable();
            $table->string('nama_pic', 100)->nullable();
            $table->string('jabatan_pic', 50)->nullable();
            $table->string('no_pic', 20)->nullable();
            $table->string('email_pic', 150)->nullable();
            $table->text('alamat_sampling')->nullable();
            $table->json('parameter_uji');
            $table->string('type_sampling', 50)->nullable();
            $table->string('fpps', 50)->nullable();
            $table->string('kategori', 50)->nullable();
            $table->integer('id_kategori_2')->nullable();
            $table->integer('id_kategori_3')->nullable();
            $table->date('tgl_tugas')->nullable();
            $table->string('sub_kategori', 100);
            $table->json('metode_sampling')->nullable();
            $table->date('tanggal_sampling')->nullable();
            $table->date('tanggal_lhp');
            $table->string('periode_analisa', 100)->nullable();
            $table->json('regulasi')->nullable();
            $table->string('file_qr', 70)->nullable();
            $table->string('file_lhp', 150)->nullable();
            $table->string('nama_karyawan', 150)->nullable();
            $table->string('jabatan_karyawan', 150)->nullable();
            $table->integer('is_active')->default(1);
            $table->string('created_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('is_approve')->nullable();
            $table->string('approved_by', 150)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_by', 70)->nullable();
            $table->string('deleted_by', 50)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('is_generated')->default(0);
            $table->string('generated_by', 150)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->integer('is_emailed')->default(0);
            $table->string('emailed_by', 150)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->integer('id_token')->nullable();
            $table->date('expired')->nullable();
            $table->tinyInteger('is_reject')->default(0);
            $table->text('reason_reject')->nullable();
            $table->json('regulasi_custom')->nullable();
            $table->integer('count_print')->nullable();
            $table->integer('count_reject')->nullable();
            $table->integer('is_revisi')->default(0);
            $table->integer('count_revisi')->nullable();
            $table->integer('is_printed')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_emisi_header_history');
    }
}
