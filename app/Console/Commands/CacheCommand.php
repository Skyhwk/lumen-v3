<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Cache\TokenCacheService;
use App\Models\UserToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class CacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:token {action} {--token=} {--user=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage token cache';

    /**
     * @var TokenCacheService
     */
    private $tokenCacheService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(TokenCacheService $tokenCacheService)
    {
        parent::__construct();
        $this->tokenCacheService = $tokenCacheService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'clear':
                $this->clearCache();
                break;
            case 'warm':
                $this->warmUpCache();
                break;
            case 'invalidate':
                $this->invalidateCache();
                break;
            case 'stats':
                $this->showCacheStats();
                break;
            case 'list':
                $this->listAllCacheTokens();
                break;
            default:
                $this->error('Available actions: clear, warm, invalidate, stats, list');
        }
    }

    private function clearCache()
    {
        Cache::flush();
        $this->info('All cache cleared successfully!');
    }

    private function warmUpCache()
    {
        // Ambil token yang aktif dalam 24 jam terakhir
        $activeTokens = UserToken::where('is_expired', false)
            ->pluck('token')
            ->toArray();

        if (empty($activeTokens)) {
            $this->info('No active tokens found to warm up');
            return;
        }

        $this->tokenCacheService->warmUpCache($activeTokens);
        $this->info('Cache warmed up with ' . count($activeTokens) . ' tokens');
    }

    private function invalidateCache()
    {
        $token = $this->option('token');
        $userId = $this->option('user');

        if ($token) {
            $this->tokenCacheService->invalidateTokenCache($token);
            $this->info('Token cache invalidated');
        }

        if ($userId) {
            $this->tokenCacheService->invalidateUserCache($userId);
            $this->info('User cache invalidated');
        }

        if (!$token && !$userId) {
            $this->error('Please provide --token or --user option');
        }
    }

    private function showCacheStats()
    {
        $stats = $this->tokenCacheService->getCacheStats();
        
        $this->info('Cache Statistics:');
        $this->table(
            ['Key', 'Value'],
            [
                ['Driver', $stats['driver']],
                ['Prefix', $stats['prefix']],
                ['Default TTL', $stats['default_ttl'] . ' seconds'],
            ]
        );
    }

    private function listAllCacheTokens()
    {
        dd(Redis::keys('*'));
        $keys = Redis::keys('*');
        if (empty($keys)) {
            $this->info('No cache keys found');
            return;
        }

        $this->info('Cache Keys:');
        foreach ($keys as $key) {
            $value = Cache::get($key); // Menggunakan Cache::get() untuk mengambil nilai dari cache
            $this->line("Key: $key, Value: $value");
        }
    }
}
?>