<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assessment_internal', function (Blueprint $table) {
            $table->id();
            
            // varchar 8 dan unique
            $table->string('batch', 8)->unique(); 
            
            $table->string('link_qr')->nullable();
            $table->string('image_qr')->nullable(); // Kolom baru untuk path gambar QR
            $table->boolean('is_publish')->default(false);
            $table->string('nama_assesment');
            
            $table->string('canceled_by')->nullable(); 
            $table->timestamp('canceled_at')->nullable();
            
            $table->string('created_by')->nullable();
            
            // Otomatis membuat kolom 'created_at' dan 'updated_at'
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assessment_internal');
    }
};