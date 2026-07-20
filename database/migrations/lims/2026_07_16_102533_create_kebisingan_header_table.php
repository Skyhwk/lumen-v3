<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKebisinganHeaderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('kebisingan_header', function (Blueprint $table) {
            $table->id();
            
            $table->string('no_sampel', 50)->nullable();
            $table->text('no_sampel_lama')->nullable();
            
            $table->bigInteger('id_parameter')->nullable();
            $table->text('parameter')->nullable();
            
            $table->boolean('is_personal')->default(0)->nullable()->comment('kebisingan personal');
            
            $table->string('ls', 30)->nullable();
            $table->string('lm', 30)->nullable();
            $table->string('leq_ls', 20)->nullable();
            $table->string('leq_lm', 20)->nullable();
            
            $table->string('leq', 25)->nullable()->comment('kebisingan 8 jam');
            
            $table->string('min', 20)->nullable();
            $table->string('max', 20)->nullable();
            
            $table->json('data_per_shift')->nullable();
            
            $table->text('notes_reject')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_by', 70)->nullable();
            
            $table->string('suhu_udara', 10)->nullable();
            $table->string('kelembapan_udara', 10)->nullable();
            
            $table->integer('lhps')->default(0)->nullable();
            
            $table->integer('is_approved')->default(0)->nullable();
            $table->string('approved_by', 70)->nullable();
            $table->timestamp('approved_at')->nullable();
            
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            
            $table->integer('status')->default(0)->nullable();
            $table->integer('is_active')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kebisingan_header');
    }
}
