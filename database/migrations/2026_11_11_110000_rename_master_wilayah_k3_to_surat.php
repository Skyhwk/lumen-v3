<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('master_wilayah_k3')) {
            return;
        }

        if (Schema::hasTable('master_wilayah_k3_sampling')) {
            DB::statement('ALTER TABLE `master_wilayah_k3_sampling` DROP PRIMARY KEY');
            DB::statement(
                'ALTER TABLE `master_wilayah_k3_sampling` '
                . 'CHANGE `master_wilayah_k3_id` `master_wilayah_surat_id` BIGINT UNSIGNED NOT NULL'
            );
            Schema::rename('master_wilayah_k3_sampling', 'master_wilayah_surat_sampling');
            DB::statement(
                'ALTER TABLE `master_wilayah_surat_sampling` '
                . 'ADD PRIMARY KEY (`master_wilayah_surat_id`, `id_master_wilayah_sampling`)'
            );
        }

        Schema::rename('master_wilayah_k3', 'master_wilayah_surat');
    }

    public function down(): void
    {
        if (!Schema::hasTable('master_wilayah_surat')) {
            return;
        }

        Schema::rename('master_wilayah_surat', 'master_wilayah_k3');

        if (Schema::hasTable('master_wilayah_surat_sampling')) {
            DB::statement('ALTER TABLE `master_wilayah_surat_sampling` DROP PRIMARY KEY');
            DB::statement(
                'ALTER TABLE `master_wilayah_surat_sampling` '
                . 'CHANGE `master_wilayah_surat_id` `master_wilayah_k3_id` BIGINT UNSIGNED NOT NULL'
            );
            Schema::rename('master_wilayah_surat_sampling', 'master_wilayah_k3_sampling');
            DB::statement(
                'ALTER TABLE `master_wilayah_k3_sampling` '
                . 'ADD PRIMARY KEY (`master_wilayah_k3_id`, `id_master_wilayah_sampling`)'
            );
        }
    }
};
