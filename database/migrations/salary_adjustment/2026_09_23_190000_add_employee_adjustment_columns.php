<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salary_adjustment_requests')) {
            return;
        }

        Schema::table('salary_adjustment_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_adjustment_requests', 'request_type')) {
                $table->string('request_type', 50)->default('penyesuaian_gaji')->after('no_document');
                $table->index('request_type');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'workflow_profile')) {
                $table->string('workflow_profile', 30)->default('full')->after('request_type');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'tanggal_efektif')) {
                $table->date('tanggal_efektif')->nullable()->after('bulan_efektif');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'tanggal_mulai')) {
                $table->date('tanggal_mulai')->nullable()->after('tanggal_efektif');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'tanggal_selesai')) {
                $table->date('tanggal_selesai')->nullable()->after('tanggal_mulai');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'tanggal_berakhir_kerja')) {
                $table->date('tanggal_berakhir_kerja')->nullable()->after('tanggal_selesai');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'new_jabatan_id')) {
                $table->unsignedBigInteger('new_jabatan_id')->nullable()->after('jabatan');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'has_salary_adjustment')) {
                $table->boolean('has_salary_adjustment')->default(true)->after('requested_tunjangan_kerja');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'receiver_manager_id')) {
                $table->unsignedBigInteger('receiver_manager_id')->nullable()->after('requested_by_id');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'receiver_responded_at')) {
                $table->timestamp('receiver_responded_at')->nullable()->after('receiver_manager_id');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'receiver_salary_decision')) {
                $table->string('receiver_salary_decision', 20)->nullable()->after('receiver_responded_at');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'receiver_adjustment_gaji')) {
                $table->decimal('receiver_adjustment_gaji', 15, 2)->nullable()->after('receiver_salary_decision');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'receiver_adjustment_tunjangan')) {
                $table->decimal('receiver_adjustment_tunjangan', 15, 2)->nullable()->after('receiver_adjustment_gaji');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'apply_status')) {
                $table->string('apply_status', 20)->default('pending')->after('applied_at');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'scheduled_apply_at')) {
                $table->date('scheduled_apply_at')->nullable()->after('apply_status');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'master_apply_error')) {
                $table->text('master_apply_error')->nullable()->after('scheduled_apply_at');
            }

            if (!Schema::hasColumn('salary_adjustment_requests', 'type_metadata')) {
                $table->json('type_metadata')->nullable()->after('catatan_tambahan');
            }
        });

        DB::connection('mysql')->table('salary_adjustment_requests')
            ->where(function ($query) {
                $query->whereNull('request_type')->orWhere('request_type', '');
            })
            ->update([
                'request_type' => 'penyesuaian_gaji',
                'workflow_profile' => 'full',
            ]);

        DB::connection('mysql')->table('salary_adjustment_requests')
            ->update([
                'has_salary_adjustment' => DB::raw('CASE WHEN COALESCE(adjustment_gaji_pokok, 0) <> 0 OR COALESCE(adjustment_tunjangan, 0) <> 0 THEN 1 ELSE 0 END'),
            ]);

        DB::connection('mysql')->table('salary_adjustment_requests')
            ->where('status', 'completed')
            ->whereNotNull('applied_at')
            ->update(['apply_status' => 'applied']);

        DB::connection('mysql')->table('salary_adjustment_requests')
            ->where('request_type', 'penyesuaian_gaji')
            ->whereNotNull('bulan_efektif')
            ->whereNull('scheduled_apply_at')
            ->update([
                'scheduled_apply_at' => DB::raw("STR_TO_DATE(CONCAT(bulan_efektif, '-01'), '%Y-%m-%d')"),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('salary_adjustment_requests')) {
            return;
        }

        Schema::table('salary_adjustment_requests', function (Blueprint $table) {
            $columns = [
                'request_type',
                'workflow_profile',
                'tanggal_efektif',
                'tanggal_mulai',
                'tanggal_selesai',
                'tanggal_berakhir_kerja',
                'new_jabatan_id',
                'has_salary_adjustment',
                'receiver_manager_id',
                'receiver_responded_at',
                'receiver_salary_decision',
                'receiver_adjustment_gaji',
                'receiver_adjustment_tunjangan',
                'apply_status',
                'scheduled_apply_at',
                'master_apply_error',
                'type_metadata',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('salary_adjustment_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
