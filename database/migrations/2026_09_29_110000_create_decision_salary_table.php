<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('decision_salary')) {
            return;
        }

        Schema::create('decision_salary', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('new_recruitment_id');
            $table->unsignedBigInteger('sallary_offer_id')->nullable();
            $table->unsignedBigInteger('personnel_request_id')->nullable();
            $table->decimal('user_amount', 15, 2)->nullable();
            $table->decimal('hrd_amount', 15, 2)->nullable();
            $table->decimal('pencadangan_upah', 15, 2)->nullable();
            $table->unsignedInteger('round')->default(1);
            $table->string('decision', 32)->default('pending');
            $table->text('reason')->nullable();
            $table->string('decided_by', 255)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('created_by', 255)->nullable();
            $table->timestamps();

            $table->index(['new_recruitment_id', 'decision'], 'decision_salary_nr_dec_idx');
            $table->index(['personnel_request_id', 'decision'], 'decision_salary_pr_dec_idx');
            $table->index(['sallary_offer_id'], 'decision_salary_offer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_salary');
    }
};
