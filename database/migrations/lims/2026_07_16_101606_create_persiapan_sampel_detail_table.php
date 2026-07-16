<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersiapanSampelDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('persiapan_sampel_detail', function (Blueprint $table) {
            $table->increments('id');
            
            $table->integer('id_persiapan_sampel_header')->nullable();
            $table->string('no_sampel', 70)->nullable();
            
            $table->json('parameters')->nullable()->comment('perlengkapan, parameter, disiapkan, tambahan, terpakai, sisa');
            $table->json('label')->nullable();
            
            $table->integer('keterangan')->nullable();
            
            $table->timestamp('created_at')->nullable();
            $table->string('created_by', 70)->nullable();
            
            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by', 70)->nullable();
            
            // Di tabel detail ini, is_active tipenya integer, bukan tinyint(1)
            $table->integer('is_active')->default(1);
            
            $table->timestamp('deleted_at')->nullable();
            $table->string('deleted_by', 70)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persiapan_sampel_detail');
    }
}
