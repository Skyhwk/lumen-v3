<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_adjustment_apply_logs')) {
            return;
        }

        Schema::create('employee_adjustment_apply_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->string('request_type', 50);
            $table->string('apply_type', 50);
            $table->string('field_name', 100)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('applied_by', 255)->nullable();
            $table->string('source', 30)->default('cron');
            $table->boolean('is_success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index('request_id');
            $table->index(['request_type', 'applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_adjustment_apply_logs');
    }
};
