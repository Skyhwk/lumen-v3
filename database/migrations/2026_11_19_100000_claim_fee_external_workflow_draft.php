<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fase 1 — Claim Fee External unified workflow (roadmap v1.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('claim_fee_external')) {
            Schema::table('claim_fee_external', function (Blueprint $table) {
                if (!Schema::hasColumn('claim_fee_external', 'sales_workflow_status')) {
                    $table->string('sales_workflow_status', 32)->default('SUBMITTED')->index();
                }
                if (!Schema::hasColumn('claim_fee_external', 'finance_status')) {
                    $table->string('finance_status', 32)->nullable()->index();
                }
                if (!Schema::hasColumn('claim_fee_external', 'required_sales_stages')) {
                    $table->json('required_sales_stages')->nullable();
                }
                if (!Schema::hasColumn('claim_fee_external', 'current_stage_index')) {
                    $table->unsignedInteger('current_stage_index')->default(0);
                }
                if (!Schema::hasColumn('claim_fee_external', 'submitted_at')) {
                    $table->dateTime('submitted_at')->nullable()->index();
                }
                if (!Schema::hasColumn('claim_fee_external', 'sales_approved_at')) {
                    $table->dateTime('sales_approved_at')->nullable();
                }
                if (!Schema::hasColumn('claim_fee_external', 'finance_locked_at')) {
                    $table->dateTime('finance_locked_at')->nullable();
                }
                if (!Schema::hasColumn('claim_fee_external', 'pending_approver_karyawan_id')) {
                    $table->unsignedInteger('pending_approver_karyawan_id')->nullable()->index();
                }
            });
        }

        if (!Schema::hasTable('claim_fee_external_approval_logs')) {
            Schema::create('claim_fee_external_approval_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('claim_fee_external_id');
                $table->string('stage', 24);
                $table->string('action', 16);
                $table->unsignedInteger('actor_karyawan_id');
                $table->unsignedInteger('expected_karyawan_id')->nullable();
                $table->string('remarks', 500)->nullable();
                $table->dateTime('created_at');

                // Nama indeks pendek (MySQL max 64 karakter).
                $table->index('claim_fee_external_id', 'cfe_applog_claim_id_idx');
                $table->index(['claim_fee_external_id', 'stage'], 'cfe_applog_claim_stage_idx');
            });
        } else {
            $this->ensureApprovalLogIndexes();
        }

        // Backfill minimal — sesuaikan setelah audit data production (Fase 1).
        if (Schema::hasTable('claim_fee_external') && Schema::hasColumn('claim_fee_external', 'sales_workflow_status')) {
            DB::table('claim_fee_external')
                ->whereNull('submitted_at')
                ->where('is_active', true)
                ->update([
                    'submitted_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]);
        }
    }

    private function ensureApprovalLogIndexes(): void
    {
        if (!Schema::hasTable('claim_fee_external_approval_logs')) {
            return;
        }

        $existing = collect(DB::select('SHOW INDEX FROM claim_fee_external_approval_logs'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('claim_fee_external_approval_logs', function (Blueprint $table) use ($existing) {
            if (!in_array('cfe_applog_claim_id_idx', $existing, true)) {
                $table->index('claim_fee_external_id', 'cfe_applog_claim_id_idx');
            }
            if (!in_array('cfe_applog_claim_stage_idx', $existing, true)) {
                $table->index(['claim_fee_external_id', 'stage'], 'cfe_applog_claim_stage_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_fee_external_approval_logs');

        if (Schema::hasTable('claim_fee_external')) {
            Schema::table('claim_fee_external', function (Blueprint $table) {
                $cols = [
                    'sales_workflow_status',
                    'finance_status',
                    'required_sales_stages',
                    'current_stage_index',
                    'submitted_at',
                    'sales_approved_at',
                    'finance_locked_at',
                    'pending_approver_karyawan_id',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('claim_fee_external', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};