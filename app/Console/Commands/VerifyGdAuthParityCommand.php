<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyGdAuthParityCommand extends Command
{
    protected $signature = 'greatday:verify-gd-auth-parity';

    protected $description = 'Bandingkan jumlah users/user_token apps vs gd_user/gd_user_token produksi (M7)';

    public function handle(): int
    {
        $appsConn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $prodConn = config('greatday.produksi_connection', config('database.default', 'mysql'));

        $appsUsers = DB::connection($appsConn)->table('users')->count();
        $gdUsers = DB::connection($prodConn)->table('gd_user')->count();
        $appsTokens = DB::connection($appsConn)->table('user_token')->count();
        $gdTokens = DB::connection($prodConn)->table('gd_user_token')->count();

        $this->table(['Sumber', 'Jumlah'], [
            ['intilab_apps.users', $appsUsers],
            ['produksi.gd_user', $gdUsers],
            ['intilab_apps.user_token', $appsTokens],
            ['produksi.gd_user_token', $gdTokens],
        ]);

        if ($gdUsers < $appsUsers) {
            $this->warn('gd_user belum lengkap — jalankan greatday:migrate-gd-auth-from-apps');
        }

        return 0;
    }
}
