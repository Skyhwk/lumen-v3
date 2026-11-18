<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('berita_acara_kejadian_disipliner')) {
            return;
        }

        Schema::table('berita_acara_kejadian_disipliner', function (Blueprint $table) {
            if (!Schema::hasColumn('berita_acara_kejadian_disipliner', 'reject_atasan_reason')) {
                $table->text('reject_atasan_reason')->nullable()->after('approval_log');
            }
            if (!Schema::hasColumn('berita_acara_kejadian_disipliner', 'rejected_atasan_at')) {
                $table->dateTime('rejected_atasan_at')->nullable()->after('reject_atasan_reason');
            }
            if (!Schema::hasColumn('berita_acara_kejadian_disipliner', 'rejected_atasan_by')) {
                $table->string('rejected_atasan_by', 150)->nullable()->after('rejected_atasan_at');
            }
            if (!Schema::hasColumn('berita_acara_kejadian_disipliner', 'rejected_atasan_karyawan_id')) {
                $table->unsignedInteger('rejected_atasan_karyawan_id')->nullable()->after('rejected_atasan_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('berita_acara_kejadian_disipliner')) {
            return;
        }

        Schema::table('berita_acara_kejadian_disipliner', function (Blueprint $table) {
            foreach (
                [
                    'rejected_atasan_karyawan_id',
                    'rejected_atasan_by',
                    'rejected_atasan_at',
                    'reject_atasan_reason',
                ] as $column
            ) {
                if (Schema::hasColumn('berita_acara_kejadian_disipliner', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};