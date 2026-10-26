<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_sender_numbers')) {
            Schema::table('wa_sender_numbers', function (Blueprint $table) {
                if (!Schema::hasColumn('wa_sender_numbers', 'number')) {
                    $table->string('number', 32)->unique();
                }
                if (!Schema::hasColumn('wa_sender_numbers', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (!Schema::hasColumn('wa_sender_numbers', 'total_sent')) {
                    $table->unsignedBigInteger('total_sent')->default(0);
                }
                if (!Schema::hasColumn('wa_sender_numbers', 'daily_sent')) {
                    $table->unsignedBigInteger('daily_sent')->default(0);
                }
                if (!Schema::hasColumn('wa_sender_numbers', 'daily_sent_date')) {
                    $table->date('daily_sent_date')->nullable();
                }
                if (!Schema::hasColumn('wa_sender_numbers', 'failure_count')) {
                    $table->unsignedInteger('failure_count')->default(0);
                }
                if (!Schema::hasColumn('wa_sender_numbers', 'last_success_at')) {
                    $table->dateTime('last_success_at')->nullable();
                }
                if (!Schema::hasColumn('wa_sender_numbers', 'last_failure_at')) {
                    $table->dateTime('last_failure_at')->nullable();
                }
                if (!Schema::hasColumn('wa_sender_numbers', 'failure_meta')) {
                    $table->json('failure_meta')->nullable();
                }
                if (!Schema::hasColumn('wa_sender_numbers', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('wa_sender_numbers', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            return;
        }

        Schema::create('wa_sender_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('total_sent')->default(0);
            $table->unsignedBigInteger('daily_sent')->default(0);
            $table->date('daily_sent_date')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->dateTime('last_success_at')->nullable();
            $table->dateTime('last_failure_at')->nullable();
            $table->json('failure_meta')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'daily_sent', 'total_sent'], 'wa_sender_distribution_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('wa_sender_numbers')) {
            Schema::drop('wa_sender_numbers');
        }
    }
};
