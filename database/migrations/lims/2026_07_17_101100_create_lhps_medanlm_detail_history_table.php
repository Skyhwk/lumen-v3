<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsMedanlmDetailHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('lhps_medanlm_detail_history', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_header')->nullable();
            $table->string('akr', 20)->nullable();
            $table->string('attr', 20)->nullable();
            $table->string('parameter', 70)->nullable();
            $table->string('no_sampel', 50)->nullable();
            $table->text('lokasi_keterangan')->nullable();
            $table->text('aktivitas_pekerjaan')->nullable();
            $table->string('sumber_radiasi', 100)->nullable();
            $table->string('waktu_paparan', 100)->nullable();
            $table->string('hasil', 50)->nullable();
            $table->string('satuan', 70)->nullable();
            $table->string('nab', 70)->nullable();
            $table->string('methode', 100)->nullable();
            $table->string('created_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('tanggal_sampling_backup', 255)->nullable();
            $table->integer('page')->nullable();
            $table->string('parameter_lab', 100)->nullable();
            $table->string('hasil_uji', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('medan_magnet', 100)->nullable();
            $table->string('rata_listrik', 100)->nullable();
            $table->string('rata_frekuensi', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_medanlm_detail_history');
    }
}
