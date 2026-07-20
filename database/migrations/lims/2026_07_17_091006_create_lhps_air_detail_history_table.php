<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsAirDetailHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lhps_air_detail_history', function (Blueprint $table) {
            $table->integer('id'); // Tanpa auto_increment
            $table->integer('id_header')->nullable();
            
            $table->string('akr', 20)->nullable();
            $table->string('parameter_lab', 50)->nullable();
            $table->string('parameter', 50)->nullable();
            $table->string('hasil_uji', 70)->nullable();
            $table->string('attr', 20)->nullable();
            
            $table->json('baku_mutu')->nullable();
            $table->string('satuan', 50)->nullable();
            $table->text('methode')->nullable();
            
            $table->string('created_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            
            $table->json('hasil_uji_json')->nullable();
            $table->text('metode_sampling')->nullable();
            $table->text('kesimpulan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_air_detail_history');
    }
}
