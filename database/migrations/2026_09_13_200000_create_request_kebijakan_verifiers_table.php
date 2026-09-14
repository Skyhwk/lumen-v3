<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestKebijakanVerifiersTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('request_kebijakan_verifiers')) {
            return;
        }

        Schema::create('request_kebijakan_verifiers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('request_kebijakan_id');
            $table->unsignedBigInteger('verifier_karyawan_id');
            $table->string('verifier_nama_lengkap', 255);
            $table->string('verifier_jabatan', 255)->nullable();
            $table->string('assigned_by', 255)->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->date('verification_date')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->text('previous_rejection_note')->nullable();
            $table->unsignedSmallInteger('verification_round')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['request_kebijakan_id', 'status'], 'idx_rkv_request_status');
            $table->index(['verifier_karyawan_id', 'status'], 'idx_rkv_verifier_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_kebijakan_verifiers');
    }
}
