<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekap generate ulang QT non-kontrak untuk pelanggan exist (order 6 bulan).
 *
 * php artisan migrate --path=database/migrations/2026_09_30_150000_create_qt_exist_reactivation_logs_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('qt_exist_reactivation_logs')) {
            Schema::create('qt_exist_reactivation_logs', function (Blueprint $table) {
                $table->id();
                $table->string('id_pelanggan', 30)->index();
                $table->string('no_qt', 80)->index()->comment('QT sumber yang di-copy');
                $table->string('no_qt_new', 80)->nullable()->index()->comment('QT hasil generate');
                $table->enum('type', ['new', 'exist'])->default('exist')->index();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['id_pelanggan', 'type', 'created_at'], 'qt_exist_react_cust_type_created_idx');
            });
            return;
        }

        Schema::table('qt_exist_reactivation_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('qt_exist_reactivation_logs', 'id_pelanggan')) {
                $table->string('id_pelanggan', 30)->index();
            }
            if (!Schema::hasColumn('qt_exist_reactivation_logs', 'no_qt')) {
                $table->string('no_qt', 80)->index()->comment('QT sumber yang di-copy');
            }
            if (!Schema::hasColumn('qt_exist_reactivation_logs', 'no_qt_new')) {
                $table->string('no_qt_new', 80)->nullable()->index()->comment('QT hasil generate');
            }
            if (!Schema::hasColumn('qt_exist_reactivation_logs', 'type')) {
                $table->enum('type', ['new', 'exist'])->default('exist')->index();
            }
            if (!Schema::hasColumn('qt_exist_reactivation_logs', 'created_at')) {
                $table->timestamp('created_at')->nullable()->useCurrent();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('qt_exist_reactivation_logs')) {
            Schema::drop('qt_exist_reactivation_logs');
        }
    }
};
