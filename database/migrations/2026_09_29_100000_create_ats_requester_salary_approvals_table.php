<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ats_requester_salary_approvals')) {
            return;
        }

        Schema::create('ats_requester_salary_approvals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('new_recruitment_id');
            $table->unsignedBigInteger('sallary_offer_id')->nullable();
            $table->unsignedBigInteger('personnel_request_id')->nullable();
            $table->decimal('user_reference_amount', 15, 2)->nullable();
            $table->decimal('hrd_offer_amount', 15, 2)->nullable();
            $table->string('decision', 32)->default('pending');
            $table->text('reason')->nullable();
            $table->string('decided_by', 255)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('created_by', 255)->nullable();
            $table->timestamps();

            $table->index(['new_recruitment_id', 'decision'], 'ats_req_sal_appr_nr_dec_idx');
            $table->index(['personnel_request_id', 'decision'], 'ats_req_sal_appr_pr_dec_idx');
            $table->index(['sallary_offer_id'], 'ats_req_sal_appr_offer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ats_requester_salary_approvals');
    }
};
