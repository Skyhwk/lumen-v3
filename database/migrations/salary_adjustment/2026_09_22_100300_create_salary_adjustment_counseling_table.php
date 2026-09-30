<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salary_adjustment_counselings')) {
            Schema::create('salary_adjustment_counselings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->unique();
                $table->unsignedBigInteger('employee_id');
                $table->date('scheduled_date');
                $table->time('scheduled_time');
                $table->enum('type', ['Online', 'Offline'])->default('Offline');
                $table->string('location', 255)->nullable();
                $table->string('meeting_link', 500)->nullable();
                $table->string('counselor_name', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 30)->default('scheduled');
                $table->text('result_notes')->nullable();
                $table->string('scheduled_by', 255)->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->string('completed_by', 255)->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('cancelled_by', 255)->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index('employee_id');
                $table->index('status');
                $table->index('scheduled_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_adjustment_counselings');
    }
};
