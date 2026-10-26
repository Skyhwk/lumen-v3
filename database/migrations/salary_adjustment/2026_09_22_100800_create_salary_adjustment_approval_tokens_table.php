<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salary_adjustment_approval_tokens')) {
            Schema::create('salary_adjustment_approval_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id');
                $table->string('approver_role', 20);
                $table->string('token', 128)->unique();
                $table->string('email_to', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('decision', 20)->nullable();
                $table->text('reject_reason')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamp('email_sent_at')->nullable();
                $table->string('created_by', 255)->nullable();
                $table->timestamps();

                $table->index(['request_id', 'approver_role']);
                $table->index('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_adjustment_approval_tokens');
    }
};
