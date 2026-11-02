<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_career_events')) {
            return;
        }

        Schema::create('employee_career_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('request_id')->nullable();
            $table->unsignedBigInteger('master_salary_id')->nullable();
            $table->string('event_type', 50);
            $table->date('effective_at')->nullable();
            $table->string('title', 255)->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('source', 50)->default('adjustment_apply');
            $table->string('no_document', 50)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_at']);
            $table->index(['employee_id', 'event_type']);
            $table->index('request_id');
            $table->unique(['employee_id', 'event_type', 'request_id'], 'employee_career_events_request_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_career_events');
    }
};
