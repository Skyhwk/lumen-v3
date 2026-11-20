<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daftar_mobil') && !Schema::hasColumn('daftar_mobil', 'kepemilikan')) {
            Schema::table('daftar_mobil', function (Blueprint $table) {
                $table->string('kepemilikan', 20)->default('internal')->after('status_gps');
                $table->index('kepemilikan');
            });

            DB::table('daftar_mobil')->whereNull('kepemilikan')->update(['kepemilikan' => 'internal']);
        }

        if (!Schema::hasTable('penggunaan_mobil_driver')) {
            Schema::create('penggunaan_mobil_driver', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('header_id');
                $table->unsignedBigInteger('driver_id');
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('deleted_by', 255)->nullable();
                $table->dateTime('deleted_at')->nullable();

                $table->index('header_id');
                $table->index(['header_id', 'is_active']);
                $table->index('driver_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('penggunaan_mobil_driver')) {
            Schema::dropIfExists('penggunaan_mobil_driver');
        }

        if (Schema::hasTable('daftar_mobil') && Schema::hasColumn('daftar_mobil', 'kepemilikan')) {
            Schema::table('daftar_mobil', function (Blueprint $table) {
                $table->dropIndex(['kepemilikan']);
                $table->dropColumn('kepemilikan');
            });
        }
    }
};