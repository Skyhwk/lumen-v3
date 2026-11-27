<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sampling_plan')) {
            return;
        }

        Schema::table('sampling_plan', function (Blueprint $table) {
            if (!Schema::hasColumn('sampling_plan', 'reject_validator_by')) {
                $table->string('reject_validator_by', 150)
                    ->nullable()
                    ->after('is_approved');
            }

            if (!Schema::hasColumn('sampling_plan', 'reject_validator_at')) {
                $table->timestamp('reject_validator_at')
                    ->nullable()
                    ->after('reject_validator_by');
            }

            if (!Schema::hasColumn('sampling_plan', 'keterangan_reject_validator')) {
                $table->text('keterangan_reject_validator')
                    ->nullable()
                    ->after('reject_validator_at');
            }

            if (!Schema::hasColumn('sampling_plan', 'riwayat_reject_validator')) {
                $table->json('riwayat_reject_validator')
                    ->nullable()
                    ->after('keterangan_reject_validator')
                    ->comment('Log riwayat reject oleh validator: [{rejected_by, rejected_at, keterangan, petugas_jadwal, no_document}]');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sampling_plan')) {
            return;
        }

        Schema::table('sampling_plan', function (Blueprint $table) {
            $cols = [
                'riwayat_reject_validator',
                'keterangan_reject_validator',
                'reject_validator_at',
                'reject_validator_by',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('sampling_plan', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
