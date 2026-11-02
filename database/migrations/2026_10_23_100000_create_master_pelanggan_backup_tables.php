<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMasterPelangganBackupTables extends Migration
{
    public function up()
    {
        $tables = [
            'master_pelanggan_backup' => 'master_pelanggan',
            'kontak_pelanggan_backup' => 'kontak_pelanggan',
            'alamat_pelanggan_backup' => 'alamat_pelanggan',
            'pic_pelanggan_backup' => 'pic_pelanggan',
        ];

        foreach ($tables as $backupTable => $sourceTable) {
            if (Schema::hasTable($backupTable) || !Schema::hasTable($sourceTable)) {
                continue;
            }

            DB::statement("CREATE TABLE `{$backupTable}` LIKE `{$sourceTable}`");
        }
    }

    public function down()
    {
        Schema::dropIfExists('pic_pelanggan_backup');
        Schema::dropIfExists('alamat_pelanggan_backup');
        Schema::dropIfExists('kontak_pelanggan_backup');
        Schema::dropIfExists('master_pelanggan_backup');
    }
}
