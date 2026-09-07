<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestKebijakanTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('request_kebijakan')) {
            Schema::create('request_kebijakan', function (Blueprint $table) {
                $table->id();
                $table->string('no_request', 30)->unique();
                $table->enum('kategori', ['new', 'revision', 'termination'])->nullable();
                $table->string('judul', 255)->nullable();
                $table->text('tujuan')->nullable();
                $table->text('ruang_lingkup')->nullable();
                $table->text('definisi')->nullable();
                $table->text('isi_ketetapan')->nullable();
                $table->text('catatan')->nullable();
                $table->enum('status', ['waiting_approval', 'approved', 'on_process', 'rejected'])->nullable();
                $table->string('request_by', 255);
                $table->timestamp('request_at');
                $table->string('approval_by', 255)->nullable();
                $table->timestamp('approval_at')->nullable();
                $table->string('rejected_by', 255)->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejected_note')->nullable();
                $table->string('deleted_by', 255)->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->index('kategori', 'idx_request_kebijakan_kategori');
                $table->index('status', 'idx_request_kebijakan_status');
                $table->index('request_by', 'idx_request_kebijakan_request_by');
                $table->index('request_at', 'idx_request_kebijakan_request_at');
                $table->index('is_active', 'idx_request_kebijakan_is_active');
                $table->index('deleted_at', 'idx_request_kebijakan_deleted_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('request_kebijakan');
    }
}
