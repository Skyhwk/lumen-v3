<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jembatan HR aset karyawan ↔ GA Fixed Asset (lihat docs/ROADMAP-HRD-ASSET-FINANCE-BRIDGE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_aset_karyawan')) {
            Schema::table('master_aset_karyawan', function (Blueprint $table) {
                if (!Schema::hasColumn('master_aset_karyawan', 'sub_kategori_aset_id')) {
                    $table->unsignedInteger('sub_kategori_aset_id')->nullable()->after('nama');
                    $table->string('ga_link_status', 30)->default('unmapped')->after('sub_kategori_aset_id');
                    $table->dateTime('ga_linked_at')->nullable()->after('ga_link_status');
                    $table->string('ga_linked_by', 255)->nullable()->after('ga_linked_at');

                    $table->index('sub_kategori_aset_id', 'mak_sub_kategori_idx');
                    $table->index('ga_link_status', 'mak_ga_link_status_idx');
                }
            });
        }

        if (Schema::hasTable('aset_karyawan')) {
            Schema::table('aset_karyawan', function (Blueprint $table) {
                if (!Schema::hasColumn('aset_karyawan', 'ga_asset_id')) {
                    $table->unsignedBigInteger('ga_asset_id')->nullable()->after('satuan');
                    $table->string('ga_link_status', 30)->default('unlinked')->after('ga_asset_id');
                    $table->dateTime('ga_linked_at')->nullable()->after('ga_link_status');
                    $table->string('ga_linked_by', 255)->nullable()->after('ga_linked_at');

                    $table->index('ga_asset_id', 'aset_karyawan_ga_asset_idx');
                    $table->index('ga_link_status', 'aset_karyawan_ga_link_status_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('aset_karyawan') && Schema::hasColumn('aset_karyawan', 'ga_asset_id')) {
            Schema::table('aset_karyawan', function (Blueprint $table) {
                $table->dropIndex('aset_karyawan_ga_asset_idx');
                $table->dropIndex('aset_karyawan_ga_link_status_idx');
                $table->dropColumn([
                    'ga_asset_id',
                    'ga_link_status',
                    'ga_linked_at',
                    'ga_linked_by',
                ]);
            });
        }

        if (Schema::hasTable('master_aset_karyawan') && Schema::hasColumn('master_aset_karyawan', 'sub_kategori_aset_id')) {
            Schema::table('master_aset_karyawan', function (Blueprint $table) {
                $table->dropIndex('mak_sub_kategori_idx');
                $table->dropIndex('mak_ga_link_status_idx');
                $table->dropColumn([
                    'sub_kategori_aset_id',
                    'ga_link_status',
                    'ga_linked_at',
                    'ga_linked_by',
                ]);
            });
        }
    }
};