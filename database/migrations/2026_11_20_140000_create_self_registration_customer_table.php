<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('self_registration_customer')) {
            return;
        }

        Schema::create('self_registration_customer', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email', 255);
            $table->string('token', 128);
            $table->text('link')->nullable();
            $table->json('id_pelanggan')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nomor_dokumen', 64)->nullable();
            $table->string('dokumen_hash', 16)->nullable();
            $table->string('signature_path', 255)->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('fail_reason', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('token', 'uq_self_reg_customer_token');
            $table->index(['email', 'status'], 'idx_self_reg_customer_email_status');
            $table->index('user_id', 'idx_self_reg_customer_user');
            $table->index('expired_at', 'idx_self_reg_customer_expired');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_registration_customer');
    }
};
