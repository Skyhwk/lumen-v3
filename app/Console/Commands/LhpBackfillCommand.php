<?php

namespace App\Console\Commands;

use App\Services\LhpBackfillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LhpBackfillCommand extends Command
{
    protected $signature = 'lhp:backfill
        {--before=2026-01-01 : Ambil data sebelum tanggal ini}
        {--from= : Ambil data mulai tanggal ini}
        {--to= : Ambil data sebelum tanggal ini}
        {--month= : Ambil data dalam bulan tertentu, format YYYY-MM}
        {--type=all : Tipe LHP, contoh pencahayaan atau all}
        {--except= : Exclude tipe LHP saat --type=all, pisahkan koma. Contoh: air,kebisingan}
        {--cfr= : Filter satu CFR/no LHP}
        {--no-sampel= : Filter satu no sampel}
        {--limit= : Batasi jumlah kandidat/group yang dicek}
        {--progress-every=25 : Tampilkan progress setiap N group, isi 0 untuk matikan}
        {--missing-only : Tampilkan/proses data yang belum punya LHP saja}
        {--connection=mysql : Koneksi target untuk cek/insert LHP dan data pendukung}
        {--order-connection= : Koneksi source khusus order_detail dan order_header}
        {--ensure-tables-from= : Clone struktur tabel LHP yang belum ada dari koneksi ini}
        {--rollback-file= : Rollback header/detail LHP dari CSV hasil backfill}
        {--full-detail : Insert detail dari sumber hasil uji tanpa fallback skeleton}
        {--allow-placeholder-detail : Izinkan insert detail minimal dari order_detail}
        {--count-only : Hitung data dan cek existing LHP tanpa approved_at/detail messages}
        {--dry-run : Cek kandidat tanpa insert data}';

    protected $description = 'Backfill data header/detail LHP historis dari order_detail';

    public function handle()
    {
        $type = $this->option('type');

        if ($type === 'list') {
            $this->info('Tipe tersedia:');
            foreach (LhpBackfillService::availableTypes() as $availableType) {
                $this->line('- ' . $availableType);
            }

            return 0;
        }

        if ($this->option('rollback-file')) {
            return $this->rollbackFromFile($this->option('rollback-file'), $this->option('connection'));
        }

        $filters = [
            'before' => $this->option('before'),
            'from' => $this->option('from'),
            'to' => $this->option('to'),
            'month' => $this->option('month'),
            'type' => $type,
            'except' => $this->option('except'),
            'cfr' => $this->option('cfr'),
            'no_sampel' => $this->option('no-sampel'),
            'limit' => $this->option('limit') ? (int) $this->option('limit') : null,
            'progress_every' => (int) $this->option('progress-every'),
            'missing_only' => (bool) $this->option('missing-only'),
            'count_only' => (bool) $this->option('count-only'),
            'full_detail' => (bool) $this->option('full-detail'),
            'allow_placeholder_detail' => (bool) $this->option('allow-placeholder-detail'),
            'dry_run' => (bool) $this->option('dry-run'),
        ];

        $this->info('===== Start LHP Backfill =====');
        $this->info('Connection : ' . $this->option('connection'));
        $this->info('Order Conn : ' . ($this->option('order-connection') ?: $this->option('connection')));
        $this->info('Before     : ' . ($filters['before'] ?: '-'));
        $this->info('From       : ' . ($filters['from'] ?: '-'));
        $this->info('To         : ' . ($filters['to'] ?: '-'));
        $this->info('Month      : ' . ($filters['month'] ?: '-'));
        $this->info('Type       : ' . $type);
        $this->info('Except     : ' . ($filters['except'] ?: '-'));
        $this->info('Dry run    : ' . ($filters['dry_run'] ? 'YES' : 'NO'));
        $this->info('Count only : ' . ($filters['count_only'] ? 'YES' : 'NO'));
        $this->info('Full detail: ' . ($filters['full_detail'] ? 'YES' : 'NO'));
        $this->info('Limit      : ' . ($filters['limit'] ?: '-'));
        $this->info('Progress   : every ' . ($filters['progress_every'] > 0 ? $filters['progress_every'] : 'OFF') . ' group');
        $this->info('Missing    : ' . ($filters['missing_only'] ? 'ONLY' : 'NO'));

        if (!$filters['dry_run'] && !$filters['count_only'] && !$filters['full_detail'] && !$filters['allow_placeholder_detail']) {
            $this->error('Non dry-run dikunci. Pilih --full-detail untuk detail hasil uji, atau --allow-placeholder-detail untuk skeleton.');
            return 1;
        }

        if (!$filters['dry_run'] && !$filters['count_only'] && !$this->option('cfr') && !$this->option('no-sampel')) {
            $this->warn('Menjalankan tanpa --dry-run dan tanpa filter spesifik.');
            $this->warn('Disarankan test dulu pakai --dry-run atau filter --cfr/--no-sampel.');
        }

        if ($this->option('ensure-tables-from')) {
            $this->ensureTables($this->option('ensure-tables-from'), $this->option('connection'), $type, $filters['dry_run']);
        }

        $filters['progress_callback'] = function (array $progress) {
            $this->line(sprintf(
                'PROGRESS %s %d/%d | candidates=%d created=%d skipped=%d failed=%d | current=%s',
                strtoupper($progress['type']),
                $progress['processed'],
                $progress['scanned'],
                $progress['candidates'],
                $progress['created'],
                $progress['skipped'],
                $progress['failed'],
                $progress['current']
            ));
        };

        $filters['failure_callback'] = function ($message) {
            $this->error($message);
        };

        try {
            $service = new LhpBackfillService($this->option('connection'), $this->option('order-connection') ?: null);
            $summary = $service->run($filters);
        } catch (\Throwable $th) {
            $this->error($th->getMessage());
            return 1;
        }

        foreach ($summary['types'] as $typeName => $result) {
            $this->line('');
            $this->info(strtoupper($typeName));
            if (isset($result['scanned'])) {
                $this->line('Scanned    : ' . $result['scanned']);
            }
            $this->line('Candidates : ' . $result['candidates']);
            $this->line('Created    : ' . $result['created']);
            $this->line('Skipped    : ' . $result['skipped']);
            $this->line('Failed     : ' . $result['failed']);

            foreach (array_slice($result['messages'], 0, 20) as $message) {
                $this->line('- ' . $message);
            }

            if (count($result['messages']) > 20) {
                $this->line('- ... ' . (count($result['messages']) - 20) . ' pesan lain disembunyikan');
            }
        }

        $this->line('');
        $this->info('TOTAL');
        if (isset($summary['total']['scanned'])) {
            $this->line('Scanned    : ' . $summary['total']['scanned']);
        }
        $this->line('Candidates : ' . $summary['total']['candidates']);
        $this->line('Created    : ' . $summary['total']['created']);
        $this->line('Skipped    : ' . $summary['total']['skipped']);
        $this->line('Failed     : ' . $summary['total']['failed']);
        $this->info('===== Finish LHP Backfill =====');

        return $summary['total']['failed'] > 0 ? 1 : 0;
    }

    private function rollbackFromFile($file, $connection)
    {
        $path = $file;
        if (!is_file($path)) {
            $path = base_path($file);
        }

        if (!is_file($path)) {
            $this->error('Rollback file tidak ditemukan: ' . $file);
            return 1;
        }

        $detailTables = [
            'lhps_air_header' => 'lhps_air_detail',
            'lhps_sinaruv_header' => 'lhps_sinaruv_detail',
            'lhps_kebisingan_header' => 'lhps_kebisingan_detail',
            'lhps_kebisingan_personal_header' => 'lhps_kebisingan_personal_detail',
            'lhps_iklim_header' => 'lhps_iklim_detail',
            'lhps_getaran_header' => 'lhps_getaran_detail',
            'lhps_pencahayaan_header' => 'lhps_pencahayaan_detail',
            'lhps_medanlm_header' => 'lhps_medanlm_detail',
            'lhps_ling_header' => 'lhps_ling_detail',
            'lhps_emisi_header' => 'lhps_emisi_detail',
            'lhps_emisi_isokinetik_header' => 'lhps_emisi_isokinetik_detail',
        ];

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $indexes = array_flip($header ?: []);
        if (!isset($indexes['table'], $indexes['id'])) {
            fclose($handle);
            $this->error('CSV rollback harus punya kolom table dan id.');
            return 1;
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $table = $row[$indexes['table']] ?? null;
            $id = (int) ($row[$indexes['id']] ?? 0);
            if ($table && $id && isset($detailTables[$table])) {
                $rows[$table][] = $id;
            }
        }
        fclose($handle);

        $deletedHeaders = 0;
        $deletedDetails = 0;
        DB::connection($connection)->transaction(function () use ($rows, $detailTables, &$deletedHeaders, &$deletedDetails, $connection) {
            foreach ($rows as $table => $ids) {
                $ids = array_values(array_unique($ids));
                $detailTable = $detailTables[$table];
                if (Schema::connection($connection)->hasTable($detailTable)) {
                    foreach (array_chunk($ids, 500) as $chunk) {
                        $deletedDetails += DB::connection($connection)->table($detailTable)->whereIn('id_header', $chunk)->delete();
                    }
                }
                foreach (array_chunk($ids, 500) as $chunk) {
                    $deletedHeaders += DB::connection($connection)->table($table)->whereIn('id', $chunk)->delete();
                }
            }
        });

        $this->info('Rollback selesai.');
        $this->line('Headers deleted : ' . $deletedHeaders);
        $this->line('Details deleted : ' . $deletedDetails);

        return 0;
    }
    private function ensureTables($sourceConnection, $targetConnection, $type, $dryRun = false)
    {
        $sourceDatabase = config("database.connections.{$sourceConnection}.database");
        $targetDatabase = config("database.connections.{$targetConnection}.database");

        if (!$sourceDatabase || !$targetDatabase) {
            throw new \RuntimeException('Database source/target tidak ditemukan di config database.');
        }

        foreach (LhpBackfillService::requiredTables($type) as $table) {
            if (Schema::connection($targetConnection)->hasTable($table)) {
                $this->line("Table exists: {$targetDatabase}.{$table}");
                continue;
            }

            if (!Schema::connection($sourceConnection)->hasTable($table)) {
                $this->warn("Source table missing: {$sourceDatabase}.{$table}");
                continue;
            }

            if ($dryRun) {
                $this->line("DRY create table: {$targetDatabase}.{$table} LIKE {$sourceDatabase}.{$table}");
                continue;
            }

            DB::connection($targetConnection)->statement(
                "CREATE TABLE `{$targetDatabase}`.`{$table}` LIKE `{$sourceDatabase}`.`{$table}`"
            );

            $this->info("Created table: {$targetDatabase}.{$table}");
        }
    }
}
