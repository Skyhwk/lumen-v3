<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateGdAppDataFromAppsCommand extends Command
{
    protected $signature = 'greatday:migrate-gd-app-data-from-apps
                            {--dry-run : Hanya tampilkan rencana}
                            {--truncate : Kosongkan gd_* sebelum copy}';

    protected $description = 'Copy notification, fcm_token, menu, permission apps → gd_* produksi (Phase 2B M8)';

    private $tables = [
        'notification' => 'gd_notification',
        'fcm_token' => 'gd_fcm_token',
        'menu' => 'gd_menu',
        'permission' => 'gd_permission',
    ];

    public function handle(): int
    {
        $appsConn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $prodConn = config('greatday.produksi_connection', config('database.default', 'mysql'));

        $appsDb = config("database.connections.{$appsConn}.database");
        $prodDb = config("database.connections.{$prodConn}.database");

        if (!$appsDb || !$prodDb) {
            $this->error('Database name tidak ditemukan di config koneksi.');

            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $truncate = (bool) $this->option('truncate');

        foreach ($this->tables as $source => $target) {
            $this->line("— {$source} → {$target}");

            $count = DB::connection($appsConn)->table($source)->count();
            $this->info("  baris apps: {$count}");

            if ($dryRun) {
                continue;
            }

            DB::connection($prodConn)->statement(
                "CREATE TABLE IF NOT EXISTS `{$prodDb}`.`{$target}` LIKE `{$appsDb}`.`{$source}`"
            );

            $existing = DB::connection($prodConn)->table($target)->count();
            if ($existing > 0 && !$truncate) {
                $this->warn("  skip copy — {$target} sudah berisi {$existing} baris (pakai --truncate untuk ulang)");

                continue;
            }

            if ($truncate && $existing > 0) {
                DB::connection($prodConn)->table($target)->truncate();
            }

            DB::connection($prodConn)->statement(
                "INSERT INTO `{$prodDb}`.`{$target}` SELECT * FROM `{$appsDb}`.`{$source}`"
            );

            $copied = DB::connection($prodConn)->table($target)->count();
            $this->info("  baris produksi ({$target}): {$copied}");
        }

        $this->info('Selesai. Set GD_USE_PRODUKSI_APP_DATA=true setelah verifikasi.');

        return 0;
    }
}
