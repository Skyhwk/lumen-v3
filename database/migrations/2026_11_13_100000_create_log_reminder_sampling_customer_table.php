<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('log_reminder_sampling_customer')) {
            return;
        }

        Schema::create('log_reminder_sampling_customer', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 32)->index();
            $table->string('mode', 20)->default('PRODUKSI')->index(); // PRODUKSI|TEST|DRY-RUN
            $table->boolean('is_dry_run')->default(false);
            $table->date('target_date')->nullable()->index();
            $table->date('jadwal_tanggal')->nullable()->index();
            $table->string('no_quotation', 191)->nullable()->index();
            $table->string('no_order', 191)->nullable()->index();
            $table->string('batch_id', 191)->nullable()->index();
            $table->string('nama_perusahaan', 500)->nullable();
            $table->string('subject', 500)->nullable();
            $table->string('fingerprint', 64)->nullable()->index();

            // sent|failed|skipped|dry_run
            $table->string('status', 20)->default('skipped')->index();
            $table->string('email_status', 20)->nullable()->index();
            $table->string('portal_status', 20)->nullable()->index();

            $table->json('email_to')->nullable();
            $table->json('portal_users')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_reminder_sampling_customer');
    }
};
