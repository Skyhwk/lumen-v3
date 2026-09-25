<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_send_logs')) {
            if (!Schema::hasColumn('wa_send_logs', 'body')) {
                Schema::table('wa_send_logs', function (Blueprint $table) {
                    $table->text('body')->nullable()->after('response_meta');
                });
            }
            return;
        }

        Schema::create('wa_send_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_number_id')->nullable();
            $table->string('sender_number', 32)->nullable();
            $table->string('destination', 32);
            $table->string('status', 16);
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->string('reason', 500)->nullable();
            $table->json('response_meta')->nullable();
            $table->text('body')->nullable();
            $table->dateTime('created_at');

            $table->index('created_at', 'wa_send_logs_created_idx');
            $table->index('destination', 'wa_send_logs_destination_idx');
            $table->index(['sender_number_id', 'created_at'], 'wa_send_logs_sender_idx');
            $table->index('status', 'wa_send_logs_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_send_logs');
    }
};
