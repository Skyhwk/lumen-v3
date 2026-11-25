<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('observasi_k3_template')) {
            Schema::create('observasi_k3_template', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 80)->unique();
                $table->string('judul_form');
                $table->text('deskripsi_program')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 150)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->string('updated_by', 150)->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('deleted_by', 150)->nullable();
                $table->timestamp('deleted_at')->nullable();
            });
        }

        if (!Schema::hasTable('observasi_k3_kategori')) {
            Schema::create('observasi_k3_kategori', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('observasi_k3_template_id')->nullable();
                $table->string('kode', 80)->unique();
                $table->string('nama');
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 150)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->string('updated_by', 150)->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('deleted_by', 150)->nullable();
                $table->timestamp('deleted_at')->nullable();

                $table->foreign('observasi_k3_template_id')
                    ->references('id')
                    ->on('observasi_k3_template')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('observasi_k3_hirarki')) {
            Schema::create('observasi_k3_hirarki', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('observasi_k3_template_id');
                $table->string('kode', 10);
                $table->string('label');
                $table->text('sub_label')->nullable();
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 150)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->string('updated_by', 150)->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('deleted_by', 150)->nullable();
                $table->timestamp('deleted_at')->nullable();

                $table->foreign('observasi_k3_template_id')
                    ->references('id')
                    ->on('observasi_k3_template')
                    ->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('observasi_k3_item')) {
            Schema::create('observasi_k3_item', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('observasi_k3_hirarki_id');
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->text('pertanyaan');
                $table->string('tipe_input', 40)->default('ya_tidak');
                $table->json('config')->nullable();
                $table->boolean('wajib_keterangan')->default(false);
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 150)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->string('updated_by', 150)->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('deleted_by', 150)->nullable();
                $table->timestamp('deleted_at')->nullable();

                $table->foreign('observasi_k3_hirarki_id')
                    ->references('id')
                    ->on('observasi_k3_hirarki')
                    ->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('observasi_k3')) {
            Schema::create('observasi_k3', function (Blueprint $table) {
                $table->id();
                $table->string('no_order')->nullable();
                $table->string('nama_perusahaan')->nullable();
                $table->json('nomor_sampel')->nullable();
                $table->json('lokasi')->nullable();
                $table->text('jenis_kegiatan')->nullable();
                $table->date('tanggal_observasi')->nullable();
                $table->time('waktu_observasi')->nullable();
                $table->string('petugas_observasi')->nullable();
                $table->json('kategori_terpilih')->nullable();
                $table->json('form_jawaban')->nullable();
                $table->json('foto')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 150)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->string('updated_by', 150)->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('deleted_by', 150)->nullable();
                $table->timestamp('deleted_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('observasi_k3');
        Schema::dropIfExists('observasi_k3_item');
        Schema::dropIfExists('observasi_k3_hirarki');
        Schema::dropIfExists('observasi_k3_kategori');
        Schema::dropIfExists('observasi_k3_template');
    }
};
