<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeactivateTrialCustomersCommand extends Command
{
    protected $signature = 'customers:deactivate-trial';

    protected $description = 'Hard delete user trial PPI H+1 hari pembuatan pada jam 23.30 beserta data terkait';

    public function handle()
    {
        $connection = 'portal_pelanggan';

        if (!Schema::connection($connection)->hasColumn('users', 'is_trial')) {
            $this->warn('Kolom is_trial belum ada di portal pelanggan.');
            return 0;
        }

        $now = Carbon::now('Asia/Jakarta');
        $cutoffCreatedDate = ($now->hour > 23 || ($now->hour === 23 && $now->minute >= 30))
            ? $now->copy()->subDay()->toDateString()
            : $now->copy()->subDays(2)->toDateString();

        $ids = DB::connection($connection)
            ->table('users')
            ->where('is_trial', 1)
            ->whereDate('created_at', '<=', $cutoffCreatedDate)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('Tidak ada user trial yang perlu dihapus.');
            return 0;
        }

        $db = DB::connection($connection);
        $schema = Schema::connection($connection);

        $db->beginTransaction();
        try {
            $teamIds = collect();
            if ($schema->hasTable('team_members')) {
                $teamIds = $db->table('team_members')
                    ->whereIn('user_id', $ids)
                    ->pluck('team_id')
                    ->unique()
                    ->filter()
                    ->values();

                $db->table('team_members')->whereIn('user_id', $ids)->delete();
            }

            if ($schema->hasTable('teams') && $teamIds->isNotEmpty()) {
                foreach ($teamIds as $teamId) {
                    $stillHasMembers = $db->table('team_members')->where('team_id', $teamId)->exists();
                    if (!$stillHasMembers) {
                        $db->table('teams')->where('id', $teamId)->delete();
                    }
                }
            }

            if ($schema->hasTable('user_tokens')) {
                $db->table('user_tokens')->whereIn('user_id', $ids)->delete();
            }

            if ($schema->hasTable('user_permissions')) {
                $db->table('user_permissions')->whereIn('user_id', $ids)->delete();
            }

            $deleted = $db->table('users')->whereIn('id', $ids)->delete();
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            $this->error('Gagal menghapus user trial: ' . $e->getMessage());
            return 1;
        }

        $this->info("Berhasil menghapus {$deleted} user trial beserta token, permission, dan team.");
        return 0;
    }
}
