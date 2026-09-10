<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promo_pengujian')) {
            return;
        }

        Schema::create('promo_pengujian', function (Blueprint $table) {
            $table->id();
            $table->string('kode_promo', 100)->unique();
            $table->string('metode', 50)
                ->comment('persentase, paket_pengujian, labeling, pengujian_gratis, free_parameter');
            $table->string('nama_diskon')->nullable();
            // Nullable while a draft has no configuration/template yet.
            // Validate the method-specific JSON structure before activation.
            $table->json('konfigurasi')->nullable();
            $table->string('status', 20)->default('draft')
                ->comment('draft, aktif, nonaktif');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'metode']);
            $table->index('metode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_pengujian');
    }
};
