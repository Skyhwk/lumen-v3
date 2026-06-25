<?php

namespace App\Helpers;

use App\Services\InternalMailService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkerInternalMailSync
{
    /** Kapasitas untuk ~40–50 user aktif bersamaan */
    private const MAX_USERS_PER_TICK = 8;
    private const MAX_SECONDS_PER_TICK = 40;
    private const MAX_SYNC_SECONDS_PER_USER = 15;
    private const MAX_PENDING_QUEUE = 100;

    public static function run(): void
    {
        $startedAt = microtime(true);
        $pending = self::pullPendingQueue();

        if (empty($pending)) {
            $pending = self::discoverStaleUsers(self::MAX_USERS_PER_TICK);
        }

        if (empty($pending)) {
            return;
        }

        $remaining = [];

        foreach ($pending as $entry) {
            if ((microtime(true) - $startedAt) >= self::MAX_SECONDS_PER_TICK) {
                $remaining[] = $entry;
                continue;
            }

            $userId = (int) ($entry['user'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $lockKey = 'mail_sync_lock:' . $userId;
            if (Cache::has($lockKey)) {
                $remaining[] = $entry;
                continue;
            }

            try {
                $service = new InternalMailService($userId, $entry['legacy'] ?? null);
                $service->runBoundedSync($entry['folder'] ?? 'inbox', !empty($entry['force']), self::MAX_SYNC_SECONDS_PER_USER);
            } catch (\Throwable $e) {
                Log::warning('WorkerInternalMailSync gagal untuk user ' . $userId . ': ' . $e->getMessage());
            }
        }

        if (!empty($remaining)) {
            self::storePendingQueue($remaining);
        }
    }

    public static function pushPending(int $idKaryawan, ?string $legacyKey, string $folder = 'inbox', bool $force = false): void
    {
        $queue = Cache::get(self::pendingCacheKey(), []);
        $queue = array_values(array_filter($queue, function ($item) use ($idKaryawan) {
            return (int) ($item['user'] ?? 0) !== $idKaryawan;
        }));

        $entry = [
            'user'   => $idKaryawan,
            'legacy' => $legacyKey,
            'folder' => $folder,
            'force'  => $force,
            'at'     => time(),
        ];

        if ($force) {
            array_unshift($queue, $entry);
        } else {
            $queue[] = $entry;
        }

        Cache::put(self::pendingCacheKey(), array_slice($queue, 0, self::MAX_PENDING_QUEUE), 600);
    }

    private static function pendingCacheKey(): string
    {
        return 'mail_sync_pending_queue';
    }

    private static function pullPendingQueue(): array
    {
        $queue = Cache::get(self::pendingCacheKey(), []);
        Cache::forget(self::pendingCacheKey());

        return is_array($queue) ? $queue : [];
    }

    private static function storePendingQueue(array $queue): void
    {
        Cache::put(self::pendingCacheKey(), array_values($queue), 600);
    }

    private static function discoverStaleUsers(int $limit): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - InternalMailService::SYNC_STALE_SECONDS);

        $rows = DB::table('mail_folder_meta')
            ->where('folder', 'inbox')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('synced_at')->orWhere('synced_at', '<', $cutoff);
            })
            ->orderBy('synced_at')
            ->limit($limit)
            ->pluck('id_karyawan');

        $pending = [];
        foreach ($rows as $idKaryawan) {
            $pending[] = [
                'user'   => (int) $idKaryawan,
                'legacy' => null,
                'folder' => 'inbox',
                'force'  => false,
                'at'     => time(),
            ];
        }

        return $pending;
    }
}
