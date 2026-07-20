<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJadwalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('jadwal', function (Blueprint $table) {
            // bigInt NOT NULL AUTO_INCREMENT bawaan Laravel
            $table->id(); 
            
            $table->integer('id_sampling')->nullable();
            $table->integer('id_cabang')->default(1)->nullable();
            $table->string('no_quotation', 50)->nullable();
            
            $table->string('parsial', 50)
                  ->nullable()
                  ->comment('parsial adalah column dari induk id yang menjadi data parsial');
                  
            $table->string('nama_perusahaan', 255)->nullable();
            $table->string('wilayah', 50)->nullable();
            $table->text('alamat')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('periode', 100)->nullable();
            
            $table->time('jam')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            
            $table->text('kategori')->nullable();
            $table->string('sampler', 50)->nullable();
            
            // Menggunakan bigInteger karena tipe aslinya bigint
            $table->bigInteger('userid')->nullable();
            
            $table->string('driver', 70)->nullable();
            $table->string('warna', 10)->nullable();
            $table->text('note')->nullable();
            
            $table->integer('durasi')->nullable();
            $table->integer('durasi_personal')->nullable();
            
            $table->integer('status')
                  ->nullable()
                  ->comment('0 booking, 1 fixed');
                  
            $table->integer('isokinetic')->default(0)->nullable();
            
            // tinyint(1) dipetakan sebagai boolean
            $table->boolean('pendampingan_k3')->default(0)->nullable();
            
            $table->integer('is_active')->default(1);
            $table->integer('flag')->default(0);
            
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            
            $table->string('updated_by', 70)->nullable();
            $table->timestamp('updated_at')->nullable();
            
            $table->string('canceled_by', 70)->nullable();
            $table->timestamp('canceled_at')->nullable();
            
            $table->integer('notif')->default(0);
            $table->integer('urutan')->nullable();
            $table->string('kendaraan', 70)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal');
    }
}
