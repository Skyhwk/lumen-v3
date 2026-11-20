<?php

namespace App\Console\Commands;

use App\Models\AlasanVoidQt;
use App\Models\Jadwal;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\SamplingPlan;
use App\Services\QuotationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Dry-run / void quotation 2025 yang sama sekali belum pernah order
 * (base no_document + seluruh revisi R1..Rn), dengan opsi filter yang punya jadwal aktif.
 *
 * Contoh:
 *   php artisan quotation:void-never-ordered --year=2025 --dry-run
 *   php artisan quotation:void-never-ordered --year=2025 --filter=with-jadwal --dry-run
 *   php artisan quotation:void-never-ordered --year=2025 --tipe=non_kontrak --execute --force
 *   php artisan quotation:void-never-ordered --year=2025 --execute --alasan="BELUM PERNAH ORDER 2025"
 */
class VoidNeverOrderedQuotationsCommand extends Command
{
    protected $signature = 'quotation:void-never-ordered
        {--year=2025 : Tahun created_at quotation}
        {--tipe=all : all|kontrak|non_kontrak}
        {--filter=never-ordered : never-ordered|with-jadwal}
        {--dry-run : Simulasi (default jika --execute tidak dipakai)}
        {--execute : Eksekusi void (flag_status=void, is_active=0)}
        {--force : Lewati konfirmasi saat --execute}
        {--limit= : Batasi jumlah baris yang diproses}
        {--alasan=BELUM PERNAH ORDER : Alasan void}
        {--sample=20 : Jumlah sample baris di tabel console}';

    protected $description = 'Dry-run / void QT tahun tertentu yang belum pernah order (base doc + revisi). Filter with-jadwal = belum order tapi punya jadwal aktif. Log file untuk tracing/rollback.';

    /** @var string */
    private $runId;

    /** @var string */
    private $logDir;

    public function handle(QuotationService $quotationService)
    {
        $year = (int) $this->option('year');
        $tipe = strtolower((string) $this->option('tipe'));
        $filter = strtolower((string) $this->option('filter'));
        $execute = (bool) $this->option('execute');
        $dryRun = !$execute || (bool) $this->option('dry-run');
        if ($execute) {
            $dryRun = false;
        }
        $limit = $this->option('limit');
        $limit = ($limit === null || $limit === '') ? null : max(1, (int) $limit);
        $sample = max(1, (int) $this->option('sample'));
        $alasan = trim((string) $this->option('alasan')) ?: 'BELUM PERNAH ORDER';

        if (!in_array($tipe, ['all', 'kontrak', 'non_kontrak'], true)) {
            $this->error('tipe harus all|kontrak|non_kontrak');
            return 1;
        }
        if (!in_array($filter, ['never-ordered', 'with-jadwal'], true)) {
            $this->error('filter harus never-ordered|with-jadwal');
            return 1;
        }
        if ($year < 2000 || $year > 2100) {
            $this->error('year tidak valid');
            return 1;
        }

        $this->runId = Carbon::now('Asia/Jakarta')->format('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8);
        $this->logDir = storage_path('logs/void-never-ordered/' . $this->runId);
        if (!File::isDirectory($this->logDir)) {
            File::makeDirectory($this->logDir, 0755, true);
        }

        $this->info('=== quotation:void-never-ordered ===');
        $this->info('Mode     : ' . ($dryRun ? 'DRY-RUN' : 'EXECUTE'));
        $this->info("Year     : {$year} (kalender Asia/Jakarta)");
        $this->info("Tipe     : {$tipe}");
        $this->info("Filter   : {$filter}");
        $this->info("Alasan   : {$alasan}");
        $this->info("Log dir  : {$this->logDir}");

        // App DB session default UTC (+00:00). Tahun bisnis = WIB, samakan dengan DBeaver (+07:00)
        // supaya created_at 2026-01-01 00:04 WIB tidak terhitung sebagai 2025-12-31 17:04 UTC.
        DB::statement("SET time_zone = '+07:00'");

        $this->writeJson('summary_start.json', [
            'run_id' => $this->runId,
            'started_at' => Carbon::now('Asia/Jakarta')->toDateTimeString(),
            'mode' => $dryRun ? 'dry-run' : 'execute',
            'year' => $year,
            'timezone' => '+07:00',
            'tipe' => $tipe,
            'filter' => $filter,
            'alasan' => $alasan,
            'limit' => $limit,
            'note' => 'Belum order = tidak ada order_header untuk base_no_document (strip revisi R#) maupun no_quotation. with-jadwal = punya sampling_plan+jadwal aktif. Filter tahun memakai session MySQL +07:00 (Asia/Jakarta).',
        ]);

        try {
            $sources = $this->sources($tipe);
            $allRows = collect();

            foreach ($sources as $source) {
                $this->line("Query {$source['tipe']} ({$filter})...");
                $rows = $this->fetchCandidates($source, $year, $filter === 'with-jadwal');
                $this->info("  {$source['tipe']}: " . count($rows));
                $allRows = $allRows->merge($rows);
            }

            $allRows = $allRows->values();
            if ($limit !== null) {
                $allRows = $allRows->take($limit)->values();
                $this->warn("Limit aktif: {$limit}");
            }

            $withJadwal = $allRows->where('jumlah_record_jadwal_aktif', '>', 0)->count();
            $withoutJadwal = $allRows->count() - $withJadwal;

            $this->newLine();
            $this->info('Ringkasan kandidat:');
            $this->table(
                ['Metrik', 'Jumlah'],
                [
                    ['Total kandidat', $allRows->count()],
                    ['Non kontrak', $allRows->where('tipe', 'non_kontrak')->count()],
                    ['Kontrak', $allRows->where('tipe', 'kontrak')->count()],
                    ['Punya jadwal aktif', $withJadwal],
                    ['Tanpa jadwal aktif', $withoutJadwal],
                ]
            );

            $this->writeJsonl('candidates.jsonl', $allRows->all());

            if ($allRows->isNotEmpty()) {
                $this->table(
                    ['Tipe', 'ID', 'No Document', 'No Quotation', 'Perusahaan', 'Flag', 'Jadwal aktif', 'Created'],
                    $allRows->take($sample)->map(function ($row) {
                        return [
                            $row['tipe'],
                            $row['id'],
                            $row['no_document'],
                            $row['no_quotation'] ?? '-',
                            mb_substr((string) ($row['nama_perusahaan'] ?? '-'), 0, 40),
                            $row['flag_status'] ?? '-',
                            $row['jumlah_record_jadwal_aktif'] ?? 0,
                            $row['created_at'] ?? '-',
                        ];
                    })->all()
                );
                if ($allRows->count() > $sample) {
                    $this->line('... dan ' . ($allRows->count() - $sample) . ' lainnya (lihat candidates.jsonl).');
                }
            }

            if ($dryRun) {
                $this->warn('DRY-RUN: tidak ada data yang diubah.');
                $this->writeJson('summary_end.json', [
                    'run_id' => $this->runId,
                    'finished_at' => Carbon::now('Asia/Jakarta')->toDateTimeString(),
                    'mode' => 'dry-run',
                    'total_candidates' => $allRows->count(),
                    'with_jadwal' => $withJadwal,
                    'without_jadwal' => $withoutJadwal,
                    'voided' => 0,
                ]);
                $this->info("Log tersimpan di: {$this->logDir}");
                return 0;
            }

            if ($allRows->isEmpty()) {
                $this->info('Tidak ada kandidat untuk di-void.');
                return 0;
            }

            if (!$this->option('force') && !$this->confirm("Void {$allRows->count()} quotation? (flag_status=void, is_active=0)")) {
                $this->warn('Dibatalkan user.');
                return 0;
            }

            $now = Carbon::now('Asia/Jakarta');
            $voided = 0;
            $skipped = 0;
            $errors = 0;
            $spTotal = 0;
            $jadwalTotal = 0;

            foreach ($allRows as $row) {
                try {
                    $result = $this->voidOne($row, $quotationService, $alasan, $now);
                    if (!$result['ok']) {
                        $skipped++;
                        $this->appendJsonl('errors.jsonl', [
                            'level' => 'skip',
                            'row' => $row,
                            'message' => $result['message'],
                            'at' => Carbon::now('Asia/Jakarta')->toDateTimeString(),
                        ]);
                        $this->warn($result['message']);
                        continue;
                    }

                    $voided++;
                    $spTotal += $result['sampling_plan'];
                    $jadwalTotal += $result['jadwal'];
                    $this->appendJsonl('executed.jsonl', $result['log']);
                    $this->appendJsonl('rollback.jsonl', $result['rollback']);
                } catch (Throwable $e) {
                    $errors++;
                    $this->appendJsonl('errors.jsonl', [
                        'level' => 'error',
                        'row' => $row,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'at' => Carbon::now('Asia/Jakarta')->toDateTimeString(),
                    ]);
                    $this->error("Gagal {$row['no_document']}: {$e->getMessage()}");
                }
            }

            $this->info("Selesai. Void: {$voided}. SP nonaktif: {$spTotal}. Jadwal nonaktif: {$jadwalTotal}. Skip: {$skipped}. Error: {$errors}.");
            $this->writeJson('summary_end.json', [
                'run_id' => $this->runId,
                'finished_at' => Carbon::now('Asia/Jakarta')->toDateTimeString(),
                'mode' => 'execute',
                'total_candidates' => $allRows->count(),
                'voided' => $voided,
                'skipped' => $skipped,
                'errors' => $errors,
                'sampling_plan_deactivated' => $spTotal,
                'jadwal_deactivated' => $jadwalTotal,
                'rollback_file' => $this->logDir . '/rollback.jsonl',
            ]);
            $this->info("Log/rollback: {$this->logDir}");
            return $errors > 0 ? 1 : 0;
        } catch (Throwable $e) {
            $this->appendJsonl('errors.jsonl', [
                'level' => 'fatal',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'at' => Carbon::now('Asia/Jakarta')->toDateTimeString(),
            ]);
            $this->error('Fatal: ' . $e->getMessage());
            return 1;
        }
    }

    private function sources(string $tipe): array
    {
        $all = [
            [
                'tipe' => 'non_kontrak',
                'table' => 'request_quotation',
                'model' => QuotationNonKontrak::class,
                'sp_status_sql' => "(sp.status_quotation IS NULL OR LOWER(TRIM(sp.status_quotation)) <> 'kontrak')",
            ],
            [
                'tipe' => 'kontrak',
                'table' => 'request_quotation_kontrak_H',
                'model' => QuotationKontrakH::class,
                'sp_status_sql' => "sp.status_quotation = 'kontrak'",
            ],
        ];

        if ($tipe === 'non_kontrak') {
            return [$all[0]];
        }
        if ($tipe === 'kontrak') {
            return [$all[1]];
        }
        return $all;
    }

    /**
     * Query mengikuti racikan user:
     * - base_no_document = strip akhiran revisi ([_/-]|spasi)+R#
     * - belum order jika base tidak pernah muncul di order_header.no_document
     *   DAN no_quotation tidak ada di order_header.no_quotation
     * - with-jadwal: inner join sampling_plan + jadwal aktif
     */
    private function fetchCandidates(array $source, int $year, bool $withJadwal): array
    {
        $table = $source['table'];
        $tipe = $source['tipe'];
        $spStatus = $source['sp_status_sql'];
        $start = sprintf('%d-01-01 00:00:00', $year);
        $end = sprintf('%d-01-01 00:00:00', $year + 1);

        // Strip revisi: sama persis racikan DBeaver — wajib ada separator sebelum R#
        // Contoh: FOO/R1, FOO-R2, FOO_R10, FOO R1 → FOO (FOOR1 tanpa separator TIDAK di-strip)
        $baseExpr = "REGEXP_REPLACE(TRIM(%s), '([_/-]|[[:space:]])+R[0-9]+$', '')";

        $jadwalCte = '';
        $jadwalJoin = '';
        $jadwalSelect = '
            0 AS jumlah_record_jadwal_aktif,
            NULL AS periode_aktif,
            NULL AS jadwal_per_tim';

        if ($withJadwal) {
            $spDeletedSql = Schema::hasColumn('sampling_plan', 'deleted_at')
                ? 'AND sp.deleted_at IS NULL'
                : '';
            $jadwalCanceledSql = Schema::hasColumn('jadwal', 'canceled_at')
                ? 'AND j.canceled_at IS NULL'
                : '';

            $jadwalSelect = '
            ass.jumlah_record_jadwal_aktif,
            ass.periode_aktif,
            ass.jadwal_per_tim';
            $jadwalJoin = 'INNER JOIN active_sampling_summary ass ON ass.quotation_id = q.id';
            $jadwalCte = ",
active_sampling_summary AS (
    SELECT
        sp.quotation_id,
        COUNT(DISTINCT j.id) AS jumlah_record_jadwal_aktif,
        GROUP_CONCAT(
            DISTINCT NULLIF(TRIM(CAST(j.periode AS CHAR)), '')
            ORDER BY CAST(j.periode AS UNSIGNED), j.periode ASC
            SEPARATOR ', '
        ) AS periode_aktif,
        GROUP_CONCAT(
            DISTINCT CONCAT_WS(
                ' | ',
                CONCAT('Tgl: ', COALESCE(DATE_FORMAT(j.tanggal, '%Y-%m-%d'), '-')),
                CONCAT('Jam: ', COALESCE(TIME_FORMAT(j.jam_mulai, '%H:%i'), '-'), '-', COALESCE(TIME_FORMAT(j.jam_selesai, '%H:%i'), '-')),
                CONCAT('Periode: ', COALESCE(NULLIF(TRIM(CAST(j.periode AS CHAR)), ''), '-')),
                CONCAT('Tim [Sampler: ', COALESCE(NULLIF(TRIM(j.sampler), '-'), '-'),
                       ' | Driver: ', COALESCE(NULLIF(TRIM(j.driver), '-'), '-'),
                       ' | Kendaraan: ', COALESCE(NULLIF(TRIM(j.kendaraan), '-'), '-'), ']')
            )
            ORDER BY j.tanggal ASC, j.jam_mulai ASC, j.periode ASC
            SEPARATOR ' || '
        ) AS jadwal_per_tim
    FROM sampling_plan sp
    INNER JOIN jadwal j ON j.id_sampling = sp.id
    WHERE {$spStatus}
      AND sp.is_active = 1
      {$spDeletedSql}
      AND j.is_active = 1
      {$jadwalCanceledSql}
    GROUP BY sp.quotation_id
)";
        }

        $qBase = sprintf($baseExpr, 'q.no_document');
        $oBase = sprintf($baseExpr, 'no_document');
        $orderDeletedSql = Schema::hasColumn('order_header', 'deleted_at')
            ? 'AND deleted_at IS NULL'
            : '';
        $qtDeletedSql = Schema::hasColumn($table, 'deleted_at')
            ? 'AND q.deleted_at IS NULL'
            : '';

        $sql = "
WITH ordered_base_docs AS (
    SELECT DISTINCT {$oBase} AS base_no_document
    FROM order_header
    WHERE no_document IS NOT NULL
      AND TRIM(no_document) <> ''
      {$orderDeletedSql}
),
quotations_year AS (
    SELECT
        q.id,
        q.no_quotation,
        q.no_document,
        q.flag_status,
        q.nama_perusahaan,
        q.grand_total,
        q.biaya_akhir,
        q.status_quotation,
        q.created_at,
        q.is_active,
        {$qBase} AS base_no_document
    FROM {$table} q
    WHERE q.created_at >= ?
      AND q.created_at < ?
      AND q.is_active = 1
      {$qtDeletedSql}
)
{$jadwalCte}
SELECT
    ? AS tipe,
    q.id,
    q.no_quotation,
    q.no_document,
    q.flag_status,
    q.base_no_document,
    q.nama_perusahaan,
    q.grand_total,
    q.biaya_akhir,
    q.status_quotation,
    q.created_at,
    {$jadwalSelect}
FROM quotations_year q
LEFT JOIN ordered_base_docs o ON q.base_no_document = o.base_no_document
{$jadwalJoin}
WHERE o.base_no_document IS NULL
  AND (
      q.no_quotation IS NULL
      OR q.no_quotation NOT IN (
          SELECT DISTINCT no_quotation
          FROM order_header
          WHERE no_quotation IS NOT NULL
            {$orderDeletedSql}
      )
  )
ORDER BY q.created_at DESC, q.no_document ASC
";

        $rows = DB::select($sql, [$start, $end, $tipe]);

        return array_map(function ($row) {
            $arr = (array) $row;
            $arr['jumlah_record_jadwal_aktif'] = (int) ($arr['jumlah_record_jadwal_aktif'] ?? 0);
            return $arr;
        }, $rows);
    }

    private function voidOne(array $row, QuotationService $quotationService, string $alasan, Carbon $now): array
    {
        return DB::transaction(function () use ($row, $quotationService, $alasan, $now) {
            $model = $row['tipe'] === 'kontrak' ? QuotationKontrakH::class : QuotationNonKontrak::class;
            $qt = $model::where('id', $row['id'])->lockForUpdate()->first();

            if (!$qt) {
                return ['ok' => false, 'message' => "Skip {$row['no_document']}: tidak ditemukan."];
            }

            if (!(int) $qt->is_active) {
                return ['ok' => false, 'message' => "Skip {$qt->no_document}: sudah nonaktif."];
            }

            if (strtolower((string) $qt->flag_status) === 'void') {
                return ['ok' => false, 'message' => "Skip {$qt->no_document}: sudah void."];
            }

            // Safety: double-check order by exact no_document / no_quotation
            if (OrderHeader::where('no_document', $qt->no_document)->whereNull('deleted_at')->exists()) {
                return ['ok' => false, 'message' => "Skip {$qt->no_document}: sudah ada order_header (no_document)."];
            }
            if (!empty($qt->no_quotation) && OrderHeader::where('no_quotation', $qt->no_quotation)->whereNull('deleted_at')->exists()) {
                return ['ok' => false, 'message' => "Skip {$qt->no_document}: sudah ada order_header (no_quotation)."];
            }

            $check = $quotationService->validateVoidQuotation($qt->no_document);
            if (empty($check['status'])) {
                return ['ok' => false, 'message' => "Skip {$qt->no_document}: " . ($check['message'] ?? 'validateVoid gagal')];
            }

            $before = [
                'tipe' => $row['tipe'],
                'id' => $qt->id,
                'no_document' => $qt->no_document,
                'no_quotation' => $qt->no_quotation,
                'flag_status' => $qt->flag_status,
                'is_active' => $qt->is_active,
                'document_status' => $qt->document_status ?? null,
                'deleted_at' => $qt->deleted_at,
                'deleted_by' => $qt->deleted_by,
            ];

            $spIds = SamplingPlan::where('no_quotation', $qt->no_document)
                ->where('is_active', true)
                ->pluck('id')
                ->all();

            $jadwalBefore = Jadwal::where('no_quotation', $qt->no_document)
                ->where('is_active', true)
                ->get(['id', 'id_sampling', 'tanggal', 'is_active'])
                ->toArray();

            $spCount = SamplingPlan::where('no_quotation', $qt->no_document)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $jadwalCount = Jadwal::where('no_quotation', $qt->no_document)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $qt->flag_status = 'void';
            $qt->is_active = false;
            $qt->document_status = 'Non Aktif';
            $qt->deleted_by = 'System:void-never-ordered';
            $qt->deleted_at = $now->format('Y-m-d H:i:s');
            $qt->save();

            $alasanVoid = new AlasanVoidQt();
            $alasanVoid->no_quotation = $qt->no_document;
            $alasanVoid->alasan = $alasan;
            $alasanVoid->keterangan = json_encode([
                [
                    'keterangan' => 'Bulk void QT belum pernah order (base document + revisi).',
                    'run_id' => $this->runId,
                    'filter' => $this->option('filter'),
                    'year' => $this->option('year'),
                    'jadwal_aktif_saat_void' => (int) ($row['jumlah_record_jadwal_aktif'] ?? 0),
                ],
            ]);
            $alasanVoid->voided_by = 'System:void-never-ordered';
            $alasanVoid->voided_at = $now->format('Y-m-d H:i:s');
            $alasanVoid->save();

            $log = [
                'at' => $now->toDateTimeString(),
                'tipe' => $row['tipe'],
                'id' => $qt->id,
                'no_document' => $qt->no_document,
                'sampling_plan_deactivated' => $spCount,
                'sampling_plan_ids' => $spIds,
                'jadwal_deactivated' => $jadwalCount,
                'alasan_void_id' => $alasanVoid->id ?? null,
            ];

            $rollback = [
                'at' => $now->toDateTimeString(),
                'action' => 'restore_quotation_and_related',
                'quotation' => $before,
                'reactivate_sampling_plan_ids' => $spIds,
                'reactivate_jadwal' => $jadwalBefore,
                'sql_hint' => [
                    'quotation_table' => $row['tipe'] === 'kontrak' ? 'request_quotation_kontrak_H' : 'request_quotation',
                    'restore_qt' => sprintf(
                        "UPDATE %s SET flag_status=%s, is_active=1, deleted_at=NULL, deleted_by=NULL, document_status=%s WHERE id=%d;",
                        $row['tipe'] === 'kontrak' ? 'request_quotation_kontrak_H' : 'request_quotation',
                        $before['flag_status'] === null ? 'NULL' : "'" . addslashes((string) $before['flag_status']) . "'",
                        $before['document_status'] === null ? 'NULL' : "'" . addslashes((string) $before['document_status']) . "'",
                        (int) $before['id']
                    ),
                ],
            ];

            return [
                'ok' => true,
                'sampling_plan' => $spCount,
                'jadwal' => $jadwalCount,
                'log' => $log,
                'rollback' => $rollback,
            ];
        });
    }

    private function writeJson(string $filename, array $payload): void
    {
        File::put(
            $this->logDir . '/' . $filename,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function writeJsonl(string $filename, array $rows): void
    {
        $path = $this->logDir . '/' . $filename;
        $fh = fopen($path, 'w');
        foreach ($rows as $row) {
            fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        }
        fclose($fh);
    }

    private function appendJsonl(string $filename, array $row): void
    {
        File::append(
            $this->logDir . '/' . $filename,
            json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }
}
