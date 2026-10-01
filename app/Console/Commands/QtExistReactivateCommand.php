<?php

namespace App\Console\Commands;

use App\Services\QtExistReactivationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class QtExistReactivateCommand extends Command
{
    protected $signature = 'qt-exist:reactivate
                            {--limit= : Batasi jumlah kandidat}
                            {--dry-run : Preview saja, tidak duplicate/generate/email}
                            {--actor=SYSTEM : Nama actor untuk created_by}
                            {--to= : Override penerima email (test), CC/BCC dikosongkan}';

    protected $description = 'Reaktivasi QT exist: duplikat QT non-kontrak → approved, generate link, kirim email customer';

    public function handle()
    {
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);

        $this->info('===== Start Command: QtExistReactivate =====');
        $this->info('Waktu (WIB): ' . Carbon::now('Asia/Jakarta')->toDateTimeString());
        $this->info('Waktu (app): ' . Carbon::now()->toDateTimeString());

        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? (int) $limitOpt : null;
        if ($limit !== null && $limit <= 0) {
            $limit = null;
        }

        $actor = trim((string) $this->option('actor')) ?: 'SYSTEM';
        $toOverride = trim((string) $this->option('to'));
        $service = (new QtExistReactivationService())
            ->withEmailOverride($toOverride !== '' ? $toOverride : null, $toOverride !== '');

        if ($this->option('dry-run')) {
            $this->warn('Dry-run aktif: preview kandidat saja (tidak insert log / generate / email).');
            $preview = $service->preview($limit);
            $this->info('Cutoff: ' . ($preview['cutoff'] ?? '-'));
            $this->info('Total kandidat: ' . ($preview['total_candidates'] ?? 0));

            $rows = collect($preview['data'] ?? [])->take(20);
            if ($rows->isNotEmpty()) {
                $this->table(
                    ['ID Pelanggan', 'Nama', 'QT Sumber', 'Last Order'],
                    $rows->map(function ($row) {
                        return [
                            $row['id_pelanggan'] ?? '-',
                            $row['nama_pelanggan'] ?? '-',
                            $row['no_qt'] ?? '-',
                            $row['last_order_at'] ?? '-',
                        ];
                    })->all()
                );
                if (($preview['total_candidates'] ?? 0) > 20) {
                    $this->line('... menampilkan 20 baris pertama saja');
                }
            }

            $this->info('===== Finish Command: QtExistReactivate (dry-run) =====');
            return 0;
        }

        if (!env('PORTALV3_LINK')) {
            $this->error('PORTALV3_LINK belum di-set. Abort.');
            return 1;
        }

        $this->info('Menjalankan execute (duplicate + generate link + email + insert log)...');
        if ($limit !== null) {
            $this->info("Limit: {$limit}");
        } else {
            $this->info('Limit: semua kandidat');
        }
        $this->info('Actor: ' . $actor);
        $this->info('Approved/emailed by: Lani Febriana Safitri');
        if ($toOverride !== '') {
            $this->warn("TEST MODE: email HANYA ke {$toOverride} (CC/BCC kosong)");
        }

        $result = $service->execute($actor, $limit);

        $this->info('Berhasil: ' . ($result['processed'] ?? 0));
        $this->info('Gagal: ' . ($result['failed_count'] ?? 0));
        $this->info('Skip: ' . ($result['skipped_count'] ?? 0));

        if (!empty($result['success'])) {
            $this->line('Contoh sukses (max 5):');
            foreach (array_slice($result['success'], 0, 5) as $item) {
                $this->line(sprintf(
                    '- %s | %s -> %s | emailed=%s | %s',
                    $item['id_pelanggan'] ?? '-',
                    $item['no_qt'] ?? '-',
                    $item['no_qt_new'] ?? '-',
                    !empty($item['emailed']) ? 'yes' : 'no',
                    $item['emailed_to'] ?? '-'
                ));
            }
        }

        if (!empty($result['failed'])) {
            $this->error('Detail gagal:');
            foreach (array_slice($result['failed'], 0, 20) as $item) {
                $this->line(sprintf(
                    '- %s | %s | %s',
                    $item['id_pelanggan'] ?? '-',
                    $item['no_qt'] ?? '-',
                    $item['error'] ?? '-'
                ));
            }
        }

        $this->info('File log: storage/logs/qt_exist_reactivation/');
        $this->info('DB log: qt_exist_reactivation_logs');
        $this->info('===== Finish Command: QtExistReactivate =====');

        return (($result['failed_count'] ?? 0) > 0) ? 1 : 0;
    }
}
