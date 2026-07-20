<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSamplingPlanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('sampling_plan', function (Blueprint $table) {
            // Menggunakan increments() karena di SQL aslinya bertipe INT, 
            // bukan BIGINT seperti bawaan method id() milik Laravel.
            $table->increments('id');
            
            $table->integer('quotation_id')->nullable();
            $table->string('no_quotation', 100)->nullable();
            $table->string('filename', 70)->nullable();
            $table->string('filename_new', 70)->nullable();
            $table->string('no_document', 100)->nullable();
            $table->string('status_quotation', 20)->nullable();
            
            $table->string('opsi_1', 50)->nullable();
            $table->string('opsi_2', 50)->nullable();
            $table->string('opsi_3', 50)->nullable();
            
            $table->enum('is_sabtu', ['Ya', 'Tidak'])->default('Tidak');
            $table->enum('is_minggu', ['Ya', 'Tidak'])->default('Tidak');
            $table->enum('is_malam', ['Ya', 'Tidak'])->default('Tidak');
            
            $table->json('tambahan')->nullable();
            $table->json('keterangan_lain')->nullable();
            
            $table->string('periode_kontrak', 15)
                  ->nullable()
                  ->comment('sebagai column bantu untk reschadule');
                  
            // tinyint(1) biasanya dipetakan sebagai boolean di Laravel
            $table->boolean('is_active')->default(1);
            
            $table->boolean('status')
                  ->default(0)
                  ->comment('triger untuk reschadule');
                  
            $table->string('status_jadwal', 7)
                  ->nullable()
                  ->comment('untuk banding dengan yang table jadwal');
                  
            $table->timestamp('timestamp_jadwal')->nullable();
            $table->string('petugas_jadwal', 255)->nullable();
            $table->date('tanggal_jadwal')->nullable();
            
            // Laravel timestamp fields
            $table->timestamp('created_at')->nullable();
            $table->string('created_by', 30)->nullable();
            
            $table->string('approved_by', 30)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_approved')->default(0)->nullable();
            
            $table->string('deleted_by', 30)->nullable();
            $table->timestamp('deleted_at')->nullable(); // Bisa juga pakai $table->softDeletes() jika modelnya pakai SoftDeletes trait
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sampling_plan');
    }
}
