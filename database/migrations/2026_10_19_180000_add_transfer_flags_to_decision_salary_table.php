<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('decision_salary')) {
            return;
        }

        if (!Schema::hasColumn('decision_salary', 'allow_hrd_transfer_jabatan')) {
            Schema::table('decision_salary', function (Blueprint $table) {
                $table->boolean('allow_hrd_transfer_jabatan')->default(false)->after('reason');
            });
        }

        if (!Schema::hasColumn('decision_salary', 'allow_hrd_transfer_divisi')) {
            Schema::table('decision_salary', function (Blueprint $table) {
                $table->boolean('allow_hrd_transfer_divisi')->default(false)->after('allow_hrd_transfer_jabatan');
            });
        }

        if (!Schema::hasColumn('decision_salary', 'tanggal_join')) {
            Schema::table('decision_salary', function (Blueprint $table) {
                $table->date('tanggal_join')->nullable()->after('allow_hrd_transfer_divisi');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('decision_salary')) {
            return;
        }

        Schema::table('decision_salary', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('decision_salary', 'allow_hrd_transfer_jabatan')) {
                $columns[] = 'allow_hrd_transfer_jabatan';
            }
            if (Schema::hasColumn('decision_salary', 'allow_hrd_transfer_divisi')) {
                $columns[] = 'allow_hrd_transfer_divisi';
            }
            if (Schema::hasColumn('decision_salary', 'tanggal_join')) {
                $columns[] = 'tanggal_join';
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
