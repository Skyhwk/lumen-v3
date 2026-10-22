<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoryUploadSoundMeterTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('history_upload_sound_meter')) {
            return;
        }

        Schema::create('history_upload_sound_meter', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('nama_file', 255);
            $table->string('path_file', 500)->nullable();
            $table->unsignedBigInteger('ukuran_file')->nullable();
            $table->string('file_hash', 64)->nullable();

            $table->string('kode_device', 100)->nullable();
            $table->string('nama_device', 255)->nullable();
            $table->string('no_sampel', 100)->nullable();
            $table->json('daftar_no_sampel')->nullable();
            $table->json('daftar_shift')->nullable();
            $table->timestamp('timestamp_awal')->nullable();
            $table->timestamp('timestamp_akhir')->nullable();

            $table->unsignedInteger('total_data')->default(0);
            $table->unsignedInteger('data_berhasil')->default(0);
            $table->unsignedInteger('data_dilewati')->default(0);
            $table->unsignedInteger('data_gagal')->default(0);

            $table->string('status', 30)->default('pending');
            $table->text('catatan')->nullable();
            $table->json('detail_gagal')->nullable();

            $table->string('uploaded_by', 255)->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();

            $table->index(['kode_device', 'no_sampel'], 'husm_device_sampel_idx');
            $table->index('file_hash', 'husm_file_hash_idx');
            $table->index('uploaded_at', 'husm_uploaded_at_idx');
            $table->index('status', 'husm_status_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('history_upload_sound_meter');
    }
}
