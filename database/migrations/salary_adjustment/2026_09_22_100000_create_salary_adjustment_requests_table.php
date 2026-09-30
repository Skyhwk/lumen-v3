<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salary_adjustment_requests')) {
            Schema::create('salary_adjustment_requests', function (Blueprint $table) {
                $table->id();
                $table->string('no_document', 50)->unique();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('requested_by_id');
                $table->string('jabatan', 100)->nullable();
                $table->decimal('current_gaji_pokok', 15, 2)->default(0);
                $table->decimal('current_tunjangan_kerja', 15, 2)->default(0);
                $table->decimal('adjustment_gaji_pokok', 15, 2)->nullable();
                $table->decimal('adjustment_tunjangan', 15, 2)->nullable();
                $table->decimal('requested_gaji_pokok', 15, 2)->default(0);
                $table->decimal('requested_tunjangan_kerja', 15, 2)->default(0);
                $table->char('bulan_efektif', 7);
                $table->text('catatan_tambahan');
                $table->string('status', 50)->default('submitted');
                $table->string('rejected_stage', 50)->nullable();
                $table->text('reject_reason')->nullable();
                $table->string('processed_by', 255)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->string('rejected_by', 255)->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->string('final_eval_approved_by', 255)->nullable();
                $table->timestamp('final_eval_approved_at')->nullable();
                $table->string('final_eval_rejected_by', 255)->nullable();
                $table->timestamp('final_eval_rejected_at')->nullable();
                $table->string('finance_approved_by', 255)->nullable();
                $table->timestamp('finance_approved_at')->nullable();
                $table->string('finance_rejected_by', 255)->nullable();
                $table->timestamp('finance_rejected_at')->nullable();
                $table->string('ibu_approved_by', 255)->nullable();
                $table->timestamp('ibu_approved_at')->nullable();
                $table->string('ibu_rejected_by', 255)->nullable();
                $table->timestamp('ibu_rejected_at')->nullable();
                $table->string('bapak_approved_by', 255)->nullable();
                $table->timestamp('bapak_approved_at')->nullable();
                $table->string('bapak_rejected_by', 255)->nullable();
                $table->timestamp('bapak_rejected_at')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->string('applied_by', 255)->nullable();
                $table->unsignedBigInteger('master_salary_id')->nullable();
                $table->string('created_by', 255);
                $table->string('updated_by', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('status');
                $table->index('employee_id');
                $table->index('requested_by_id');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_adjustment_requests');
    }
};
