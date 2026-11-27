<?php

namespace App\Console\Commands;

use App\Models\Printers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPrinterStatusCommand extends Command
{
    protected $signature = 'printers:sync-status';

    protected $description = 'Sinkron status printer dari Printer Manager (cocokkan server + share_name)';

    public function handle(): int
    {
        $url = rtrim((string) env('PRINTER_MANAGER_URL', 'https://apps.intilab.com/central/printers'), '/');

        try {
            $response = Http::timeout(60)->acceptJson()->get($url);
        } catch (\Throwable $e) {
            $this->error('Gagal fetch Printer Manager: ' . $e->getMessage());
            Log::error('printers:sync-status: fetch gagal', ['url' => $url, 'error' => $e->getMessage()]);

            return 1;
        }

        if (!$response->successful()) {
            $this->error('Printer Manager merespons HTTP ' . $response->status());
            Log::error('printers:sync-status: HTTP error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return 1;
        }

        $payload = $response->json();
        $remoteList = [];

        if (is_array($payload)) {
            if (isset($payload['data']) && is_array($payload['data'])) {
                $remoteList = $payload['data'];
            } else {
                $remoteList = $payload;
            }
        }

        $statusByKey = [];
        foreach ($remoteList as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = $this->matchKey($item['server'] ?? null, $item['share_name'] ?? null);
            if ($key === '') {
                continue;
            }
            $statusByKey[$key] = $item['status'] ?? null;
        }

        $updated = 0;
        $unchanged = 0;
        $unmatched = 0;

        Printers::query()
            ->select(['id', 'server', 'share_name', 'status'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($statusByKey, &$updated, &$unchanged, &$unmatched) {
                foreach ($rows as $printer) {
                    $key = $this->matchKey($printer->server, $printer->share_name);
                    if ($key === '' || !array_key_exists($key, $statusByKey)) {
                        $unmatched++;
                        continue;
                    }

                    $newStatus = $statusByKey[$key];
                    if ((string) $printer->status === (string) $newStatus) {
                        $unchanged++;
                        continue;
                    }

                    Printers::where('id', $printer->id)->update(['status' => $newStatus]);
                    $updated++;
                }
            });

        $message = "Sync printer: {$updated} diupdate, {$unchanged} sudah sama, {$unmatched} tidak ditemukan di API";
        $this->info($message);
        Log::info('printers:sync-status: selesai', [
            'updated' => $updated,
            'unchanged' => $unchanged,
            'unmatched' => $unmatched,
            'remote_count' => count($remoteList),
        ]);

        return 0;
    }

    private function matchKey($server, $shareName): string
    {
        $server = trim((string) $server);
        $shareName = trim((string) $shareName);

        if ($server === '' || $shareName === '') {
            return '';
        }

        return strtolower($server) . '|' . strtolower($shareName);
    }
}