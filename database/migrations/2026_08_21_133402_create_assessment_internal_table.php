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
        if (Schema::hasTable('assessment_internal')) {
            return;
        }

        Schema::create('assessment_internal', function (Blueprint $table) {
            $table->id();
            
            // varchar 8 dan unique
            $table->string('batch', 8)->unique(); 
            
            $table->string('nama_assesment');
            $table->string('token', 64)->nullable()->unique();
            $table->string('link_qr')->nullable();
            $table->string('image_qr')->nullable(); // Kolom baru untuk path gambar QR
            $table->boolean('is_publish')->default(false);
            $table->boolean('is_link_active')->default(false);
            $table->boolean('is_completed_profile')->default(false);
            $table->timestamp('link_deactivated_at')->nullable() ;
            $table->json('category_question')->nullable();
            
            $table->string('created_by')->nullable();
            $table->string('canceled_by')->nullable(); 
            $table->timestamp('canceled_at')->nullable();
            
            
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
        // Non-destructive: tabel induk assessment mungkin sudah berisi data produksi.
    }
};
