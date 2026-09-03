<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('personnel_requests', function (Blueprint $table) {
            // Mengubah tipe kolom menjadi JSON
            $table->json('assesment_question_category')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personnel_requests', function (Blueprint $table) {
            // Kembalikan ke tipe data sebelumnya (misal: string atau text)
            // Silakan sesuaikan 'string' dengan tipe data asli Anda sebelum diubah
            $table->string('assesment_question_category')->change(); 
        });
    }
};