<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDraftingKebijakanTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('drafting_kebijakan')) {
            Schema::create('drafting_kebijakan', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_kebijakan_id')->unique();
                $table->string('judul', 255)->nullable();
                $table->text('tujuan')->nullable();
                $table->text('ruang_lingkup')->nullable();
                $table->text('definisi')->nullable();
                $table->text('isi_ketetapan')->nullable();
                $table->text('catatan_legal')->nullable();
                $table->enum('status', ['in_progress', 'submitted'])->default('in_progress');
                $table->string('processed_by', 255);
                $table->timestamp('processed_at');
                $table->string('submitted_by', 255)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->index('status', 'idx_drafting_kebijakan_status');
                $table->foreign('request_kebijakan_id')
                    ->references('id')
                    ->on('request_kebijakan')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('drafting_kebijakan');
    }
}
