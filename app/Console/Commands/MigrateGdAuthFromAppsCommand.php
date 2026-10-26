<?php

namespace App\Console\Commands;

use App\Models\Gd\GdMigrationMap;
use App\Models\Gd\GdUser;
use App\Models\Gd\GdUserToken;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateGdAuthFromAppsCommand extends Command
{
    protected $signature = 'greatday:migrate-gd-auth-from-apps {--dry-run : Hanya hitung}';

    protected $description = 'Backfill intilab_apps users + user_token → gd_user / gd_user_token (Phase 2B M7)';

    public function handle(): int
    {
        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $dryRun = (bool) $this->option('dry-run');

        $users = DB::connection($conn)->table('users')->orderBy('user_id')->get();
        $this->info('users: ' . $users->count());

        foreach ($users as $row) {
            $legacyId = (int) $row->user_id;
            if ($this->mapped('users', $legacyId)) {
                continue;
            }

            if ($dryRun) {
                continue;
            }

            GdUser::updateOrCreate(
                ['id' => $legacyId],
                [
                    'karyawan_id' => $legacyId,
                    'username' => $row->username ?? null,
                    'email' => $row->email ?? null,
                    'password' => $row->password,
                    'is_active' => (bool) ($row->is_active ?? true),
                    'created_by' => $row->created_by ?? null,
                    'created_at' => $row->created_at ?? null,
                    'updated_by' => $row->updated_by ?? null,
                    'updated_at' => $row->updated_at ?? null,
                    'deleted_by' => $row->deleted_by ?? null,
                    'deleted_at' => $row->deleted_at ?? null,
                ]
            );

            $this->map('users', $legacyId, 'gd_user', $legacyId);
        }

        $tokens = DB::connection($conn)->table('user_token')->orderBy('id')->get();
        $this->info('user_token: ' . $tokens->count());
        $tokenCount = 0;

        foreach ($tokens as $row) {
            if ($dryRun) {
                $tokenCount++;
                continue;
            }

            GdUserToken::updateOrCreate(
                ['token' => $row->token],
                [
                    'karyawan_id' => (int) $row->user_id,
                    'create_date' => $row->create_date,
                    'expired' => $row->expired,
                    'is_logged_in' => (bool) ($row->is_logged_in ?? false),
                    'is_expired' => (bool) ($row->is_expired ?? false),
                    'type' => $row->type ?? null,
                ]
            );
            $tokenCount++;
        }

        $this->info('Token rows processed: ' . $tokenCount);
        $this->info('Selesai. Set GD_USE_PRODUKSI_AUTH=true setelah verifikasi login.');

        return 0;
    }

    private function mapped(string $table, int $oldId): bool
    {
        return GdMigrationMap::where([
            'old_connection' => 'intilab_apps',
            'old_table' => $table,
            'old_id' => $oldId,
        ])->exists();
    }

    private function map(string $oldTable, int $oldId, string $newTable, int $newId): void
    {
        GdMigrationMap::firstOrCreate([
            'old_connection' => 'intilab_apps',
            'old_table' => $oldTable,
            'old_id' => $oldId,
        ], [
            'new_table' => $newTable,
            'new_id' => $newId,
            'migrated_at' => Carbon::now(),
        ]);
    }
}
