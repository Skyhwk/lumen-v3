<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGreatdayTokenImpersonationColumns extends Migration
{
    public function up()
    {
        $this->addColumns(config('greatday.apps_connection', 'intilab_apps'), 'user_token', 'user_id');
        $this->addColumns(config('greatday.produksi_connection', env('DB_CONNECTION', 'mysql')), 'gd_user_token', 'karyawan_id');
    }

    public function down()
    {
        $this->dropColumns(config('greatday.apps_connection', 'intilab_apps'), 'user_token');
        $this->dropColumns(config('greatday.produksi_connection', env('DB_CONNECTION', 'mysql')), 'gd_user_token');
    }

    private function addColumns(string $connection, string $table, string $afterColumn): void
    {
        if (!Schema::connection($connection)->hasTable($table)) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($connection, $table, $afterColumn) {
            if (!Schema::connection($connection)->hasColumn($table, 'is_impersonate')) {
                $blueprint->boolean('is_impersonate')->default(false)->after($afterColumn);
            }
            if (!Schema::connection($connection)->hasColumn($table, 'impersonator_karyawan_id')) {
                $blueprint->unsignedBigInteger('impersonator_karyawan_id')->nullable()->after('is_impersonate');
            }
        });
    }

    private function dropColumns(string $connection, string $table): void
    {
        if (!Schema::connection($connection)->hasTable($table)) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($connection, $table) {
            if (Schema::connection($connection)->hasColumn($table, 'impersonator_karyawan_id')) {
                $blueprint->dropColumn('impersonator_karyawan_id');
            }
            if (Schema::connection($connection)->hasColumn($table, 'is_impersonate')) {
                $blueprint->dropColumn('is_impersonate');
            }
        });
    }
}
