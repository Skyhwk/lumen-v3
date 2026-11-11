<?php

namespace App\Console\Commands;

use App\Models\MasterKaryawan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\customer\Users as PortalUsers;
use App\Services\PortalNotificationService;
use App\Services\SendEmail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * Notifikasi H-14 sampling ke PIC customer.
 *
 * Contoh:
 *  php artisan sampling:notify-h14 --dry-run
 *  php artisan sampling:notify-h14 --dry-run --email=dev@intilab.com
 *  php artisan sampling:notify-h14 --email=dev1@intilab.com,dev2@intilab.com --limit=1
 *  php artisan sampling:notify-h14 --date=2026-11-24 --dry-run
 *
 * --email = mode test: email + notifikasi portal diarahkan ke akun portal
 * dengan email tersebut (bukan PIC quotation).
 *
 * Cron terpisah (contoh jam 15:00 WIB):
 *  0 15 * * * cd /path/lumen-v3 && php artisan sampling:notify-h14 >> /dev/null 2>&1
 */
class NotifySamplingH14Command extends Command
{
    protected $signature = 'sampling:notify-h14
                            {--dry-run : Simulasi saja, tidak kirim email/portal}
                            {--email= : Email tujuan custom (koma). Mode test ke akun portal + email itu}
                            {--date= : Override tanggal target Y-m-d (default CURDATE+14)}
                            {--limit= : Batasi jumlah tim yang diproses}
                            {--days=14 : Offset hari ke depan (default 14)}
                            {--debug : Tampilkan DB connection + daftar batch_id tim}';

    protected $description = 'Kirim email + notifikasi portal reminder sampling H-14 (Sen-Min)';

    private $logger;

    public function handle(): int
    {
        Carbon::setLocale('id');
        $this->logger = Log::channel('sampling_h14');

        $dryRun = (bool) $this->option('dry-run');
        $days = max(1, (int) $this->option('days'));
        $limit = $this->option('limit') !== null && $this->option('limit') !== ''
            ? max(1, (int) $this->option('limit'))
            : null;

        try {
            $targetDate = $this->resolveTargetDate($days);
            $testEmails = $this->parseEmails((string) $this->option('email'));
            $testMode = $testEmails !== [];
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $runId = Carbon::now('Asia/Jakarta')->format('Ymd_His');
        $modeLabel = $dryRun ? 'DRY-RUN' : ($testMode ? 'TEST' : 'PRODUKSI');

        $this->info("=== sampling:notify-h14 [{$modeLabel}] ===");
        $this->line('Run ID     : ' . $runId);
        $this->line('Hari ini   : ' . Carbon::now('Asia/Jakarta')->toDateString());
        $this->line('Target H+' . $days . ' : ' . $targetDate . ' (' . Carbon::parse($targetDate)->translatedFormat('l, d F Y') . ')');
        if ($testMode) {
            $this->line('Email test : ' . implode(', ', $testEmails));
        }
        if ($limit !== null) {
            $this->line('Limit      : ' . $limit);
        }

        if ($this->option('debug')) {
            $this->printDbDebug($targetDate);
        }

        $this->logger->info('START', [
            'run_id' => $runId,
            'mode' => $modeLabel,
            'target_date' => $targetDate,
            'days' => $days,
            'dry_run' => $dryRun,
            'test_mode' => $testMode,
            'test_emails' => $testEmails,
            'limit' => $limit,
        ]);

        $teams = $this->fetchTeams($targetDate);
        if ($limit !== null) {
            $teams = array_slice($teams, 0, $limit);
        }

        $this->info('Tim ditemukan: ' . count($teams));
        $this->logger->info('TEAMS_LOADED', [
            'run_id' => $runId,
            'count' => count($teams),
            'batch_ids' => array_column($teams, 'batch_id'),
        ]);

        $snapshot = $this->buildSnapshot($targetDate, $teams);
        $changes = $this->diffWithPreviousSnapshot($targetDate, $snapshot);
        $this->writeSnapshot($targetDate, $runId, $snapshot, $changes);

        if ($changes['added'] || $changes['removed'] || $changes['changed']) {
            $this->warn('Perubahan vs snapshot sebelumnya:');
            $this->line('  + added   : ' . count($changes['added']));
            $this->line('  - removed : ' . count($changes['removed']));
            $this->line('  ~ changed : ' . count($changes['changed']));
            $this->logger->info('CHANGES_DETECTED', [
                'run_id' => $runId,
                'target_date' => $targetDate,
                'added' => $changes['added'],
                'removed' => $changes['removed'],
                'changed' => $changes['changed'],
            ]);
        } else {
            $this->line('Tidak ada perubahan dibanding snapshot sebelumnya (atau snapshot pertama).');
            $this->logger->info('NO_CHANGES', ['run_id' => $runId, 'target_date' => $targetDate]);
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($teams as $index => $team) {
            $no = $index + 1;
            $payload = $this->enrichTeam($team);

            if ($payload === null) {
                $skipped++;
                $this->warn("[{$no}] SKIP {$team['no_quotation']} — quotation/PIC tidak ditemukan");
                $this->logger->warning('SKIP_NO_QUOTATION', [
                    'run_id' => $runId,
                    'no_quotation' => $team['no_quotation'],
                    'batch_id' => $team['batch_id'],
                ]);
                continue;
            }

            $subject = $this->buildSubject($payload);
            $body = $this->buildBody($payload);
            $recipients = $this->resolveRecipients($payload, $testEmails);
            $portalUsers = $this->resolvePortalUsers($recipients['to']);

            $this->line("[{$no}] {$payload['no_quotation']} | {$payload['no_order']} | {$payload['nama_perusahaan']}");
            $this->line('     TO: ' . implode(', ', $recipients['to']));
            $this->printPortalTargets($portalUsers, $recipients['to']);

            $this->logger->info('PREPARE_NOTIFY', [
                'run_id' => $runId,
                'no_quotation' => $payload['no_quotation'],
                'no_order' => $payload['no_order'],
                'batch_id' => $payload['batch_id'],
                'jadwal_tanggal' => $payload['jadwal_tanggal'],
                'kategori' => $payload['kategori_summary'],
                'sampler' => $payload['sampler_list'],
                'to' => $recipients['to'],
                'cc' => $recipients['cc'],
                'subject' => $subject,
                'fingerprint' => $payload['fingerprint'],
                'portal_users' => array_map(function ($u) {
                    return ['id' => $u->id, 'email' => $u->email];
                }, $portalUsers),
            ]);

            if ($dryRun) {
                $this->line('     DRY-RUN: email & portal tidak dikirim');
                $sent++;
                continue;
            }

            if ($recipients['to'] === []) {
                $skipped++;
                $this->warn('     SKIP: tidak ada email tujuan valid');
                $this->logger->warning('SKIP_NO_RECIPIENT', [
                    'run_id' => $runId,
                    'no_quotation' => $payload['no_quotation'],
                ]);
                continue;
            }

            $emailOk = false;
            try {
                $ok = SendEmail::where('to', $recipients['to'][0])
                    ->where('cc', array_slice($recipients['to'], 1))
                    ->where('bcc', $recipients['cc'])
                    ->where('subject', $subject)
                    ->where('body', $body)
                    ->where('karyawan', 'SYSTEM_H14')
                    ->fromAdmsales()
                    ->send();

                if ($ok) {
                    $emailOk = true;
                    $this->info('     EMAIL SENT');
                    $this->logger->info('EMAIL_SENT', [
                        'run_id' => $runId,
                        'no_quotation' => $payload['no_quotation'],
                        'to' => $recipients['to'],
                    ]);
                } else {
                    $this->error('     EMAIL FAILED send()=false');
                    $this->logger->error('EMAIL_FAILED', [
                        'run_id' => $runId,
                        'no_quotation' => $payload['no_quotation'],
                    ]);
                }
            } catch (\Throwable $e) {
                $this->error('     EMAIL FAILED: ' . $e->getMessage());
                $this->logger->error('EMAIL_EXCEPTION', [
                    'run_id' => $runId,
                    'no_quotation' => $payload['no_quotation'],
                    'message' => $e->getMessage(),
                ]);
            }

            $portalOk = $this->sendPortalNotifications($portalUsers, $payload, $runId);

            if ($emailOk || $portalOk) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $summary = compact('sent', 'skipped', 'failed') + ['total' => count($teams)];
        $this->info('Selesai. sent=' . $sent . ' skipped=' . $skipped . ' failed=' . $failed);
        $this->logger->info('FINISH', ['run_id' => $runId, 'summary' => $summary, 'mode' => $modeLabel]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveTargetDate(int $days): string
    {
        $override = trim((string) $this->option('date'));
        if ($override !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $override)) {
                throw new \InvalidArgumentException('--date harus format Y-m-d');
            }
            return $override;
        }

        return Carbon::now('Asia/Jakarta')->addDays($days)->toDateString();
    }

    private function parseEmails(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $emails = [];
        foreach (preg_split('/[,;]+/', $raw) as $item) {
            $email = strtolower(trim($item));
            if ($email === '') {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Email tidak valid: ' . $email);
            }
            $emails[] = $email;
        }

        return array_values(array_unique($emails));
    }

    private function printDbDebug(string $targetDate): void
    {
        $dbName = DB::connection()->getDatabaseName();
        $host = config('database.connections.' . config('database.default') . '.host');
        $this->warn("DEBUG DB : {$host} / {$dbName}");

        $counts = DB::selectOne("
            SELECT
              (SELECT COUNT(*) FROM jadwal j
                WHERE j.is_active = 1 AND j.tanggal = ? AND j.no_quotation IS NOT NULL) AS raw_rows,
              (SELECT COUNT(*) FROM (
                  SELECT 1
                  FROM jadwal j
                  WHERE j.is_active = 1
                    AND j.tanggal = ?
                    AND j.no_quotation IS NOT NULL
                  GROUP BY
                    j.id_sampling, j.parsial, j.no_quotation, j.tanggal, j.periode, j.nama_perusahaan,
                    j.isokinetic, j.pendampingan_k3, j.durasi, j.driver, j.kategori, j.status,
                    j.jam_mulai, j.jam_selesai, j.warna, j.note, j.urutan, j.wilayah, j.id_cabang, j.kendaraan
                ) sp) AS sp_teams,
              (SELECT COUNT(*) FROM (
                  SELECT 1
                  FROM jadwal j
                  WHERE j.is_active = 1
                    AND j.tanggal = ?
                    AND j.no_quotation IS NOT NULL
                    AND EXISTS (
                      SELECT 1 FROM order_header oh
                      WHERE oh.no_document = j.no_quotation AND oh.is_active = 1
                    )
                  GROUP BY
                    j.id_sampling, j.parsial, j.no_quotation, j.tanggal, j.periode, j.nama_perusahaan,
                    j.isokinetic, j.pendampingan_k3, j.durasi, j.driver, j.kategori, j.status,
                    j.jam_mulai, j.jam_selesai, j.warna, j.note, j.urutan, j.wilayah, j.id_cabang, j.kendaraan
                ) cmd) AS cmd_teams_with_oh
        ", [$targetDate, $targetDate, $targetDate]);

        $this->line('DEBUG raw jadwal rows        : ' . ($counts->raw_rows ?? 0));
        $this->line('DEBUG SP-style teams         : ' . ($counts->sp_teams ?? 0));
        $this->line('DEBUG command teams (+ OH)   : ' . ($counts->cmd_teams_with_oh ?? 0));
    }

    private function fetchTeams(string $targetDate): array
    {
        // Group by keys MUST match SamplingPlanController@index agar jumlah tim sama dengan UI.
        $rows = DB::table('jadwal as j')
            ->select([
                'j.no_quotation',
                'j.nama_perusahaan',
                'j.tanggal as jadwal_tanggal',
                'j.periode',
                'j.parsial',
                'j.id_sampling',
                'j.jam_mulai',
                'j.jam_selesai',
                'j.durasi',
                'j.status',
                'j.kategori',
                'j.id_cabang',
                'j.wilayah',
                'j.kendaraan',
                DB::raw('GROUP_CONCAT(DISTINCT j.id ORDER BY j.id) as batch_id'),
                DB::raw("GROUP_CONCAT(DISTINCT j.sampler ORDER BY j.id SEPARATOR ', ') as sampler"),
                DB::raw('MAX(j.updated_at) as updated_at'),
                DB::raw('MAX(j.updated_by) as updated_by'),
            ])
            ->where('j.is_active', true)
            ->whereNotNull('j.no_quotation')
            ->where('j.no_quotation', '!=', '')
            ->where('j.tanggal', $targetDate)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('order_header as oh')
                    ->whereColumn('oh.no_document', 'j.no_quotation')
                    ->where('oh.is_active', true);
            })
            ->groupBy(
                'j.id_sampling',
                'j.parsial',
                'j.no_quotation',
                'j.tanggal',
                'j.periode',
                'j.nama_perusahaan',
                'j.isokinetic',
                'j.pendampingan_k3',
                'j.durasi',
                'j.driver',
                'j.kategori',
                'j.status',
                'j.jam_mulai',
                'j.jam_selesai',
                'j.warna',
                'j.note',
                'j.urutan',
                'j.wilayah',
                'j.id_cabang',
                'j.kendaraan'
            )
            ->orderBy('j.no_quotation')
            ->orderBy('j.jam_mulai')
            ->get();

        return $rows->map(function ($row) {
            return [
                'no_quotation' => (string) $row->no_quotation,
                'nama_perusahaan' => (string) $row->nama_perusahaan,
                'jadwal_tanggal' => Carbon::parse($row->jadwal_tanggal)->toDateString(),
                'periode' => $row->periode,
                'parsial' => $row->parsial,
                'id_sampling' => $row->id_sampling,
                'jam_mulai' => $row->jam_mulai ? substr((string) $row->jam_mulai, 0, 5) : '-',
                'jam_selesai' => $row->jam_selesai ? substr((string) $row->jam_selesai, 0, 5) : '-',
                'durasi' => (int) $row->durasi,
                'status' => $row->status,
                'kategori' => $row->kategori,
                'id_cabang' => $row->id_cabang,
                'wilayah' => $row->wilayah,
                'kendaraan' => $row->kendaraan,
                'batch_id' => (string) $row->batch_id,
                'sampler' => (string) $row->sampler,
                'updated_at' => $row->updated_at,
                'updated_by' => $row->updated_by,
            ];
        })->all();
    }

    private function enrichTeam(array $team): ?array
    {
        $type = $this->quotationType($team['no_quotation']);
        $quotation = $this->loadQuotation($team['no_quotation'], $type);
        if (!$quotation) {
            return null;
        }

        $order = DB::table('order_header')
            ->where('no_document', $team['no_quotation'])
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first(['id', 'no_order', 'no_document']);

        if (!$order) {
            return null;
        }

        $kategoriItems = $this->parseKategoriItems($team['kategori']);
        $kategoriSummary = $this->summarizeKategori($kategoriItems);
        $samplerList = array_values(array_filter(array_map('trim', explode(',', (string) $team['sampler']))));

        $picOrderName = trim((string) ($quotation->nama_pic_order ?? ''));
        $picSamplingName = trim((string) ($quotation->nama_pic_sampling ?? ''));
        $samePic = $this->normalizeName($picOrderName) !== ''
            && $this->normalizeName($picOrderName) === $this->normalizeName($picSamplingName);

        $sales = null;
        if (!empty($quotation->sales_id)) {
            $sales = MasterKaryawan::where('id', $quotation->sales_id)->first();
        }

        $payload = array_merge($team, [
            'type' => $type,
            'no_order' => (string) $order->no_order,
            'kategori_items' => $kategoriItems,
            'kategori_summary' => $kategoriSummary,
            'sampler_list' => $samplerList,
            'durasi_label' => $this->formatDurasi($team['durasi']),
            'tanggal_label' => Carbon::parse($team['jadwal_tanggal'])->translatedFormat('l, d F Y'),
            'pic_order' => [
                'nama' => $picOrderName,
                'jabatan' => trim((string) ($quotation->jabatan_pic_order ?? '')),
                'no' => trim((string) ($quotation->no_pic_order ?? '')),
                'email' => strtolower(trim((string) ($quotation->email_pic_order ?? ''))),
            ],
            'pic_sampling' => [
                'nama' => $picSamplingName,
                'jabatan' => trim((string) ($quotation->jabatan_pic_sampling ?? '')),
                'no' => trim((string) ($quotation->no_tlp_pic_sampling ?? '')),
                'email' => strtolower(trim((string) ($quotation->email_pic_sampling ?? ''))),
            ],
            'same_pic' => $samePic,
            'sales' => [
                'nama' => $sales ? trim((string) $sales->nama_lengkap) : '-',
                'kontak' => $sales ? (string) ($sales->no_telpon_sales ?: ($sales->email ?? '-')) : '-',
                'email' => $sales ? strtolower(trim((string) ($sales->email ?? ''))) : '',
            ],
        ]);

        $payload['fingerprint'] = sha1(json_encode([
            'no_quotation' => $payload['no_quotation'],
            'batch_id' => $payload['batch_id'],
            'jadwal_tanggal' => $payload['jadwal_tanggal'],
            'jam_mulai' => $payload['jam_mulai'],
            'durasi' => $payload['durasi'],
            'kategori' => $payload['kategori_summary'],
            'sampler' => $payload['sampler_list'],
            'kendaraan' => $payload['kendaraan'],
            'status' => $payload['status'],
        ], JSON_UNESCAPED_UNICODE));

        return $payload;
    }

    private function quotationType(string $noDocument): string
    {
        $parts = explode('/', $noDocument);
        $code = strtoupper((string) ($parts[1] ?? ''));
        return $code === 'QTC' ? 'QTC' : 'QT';
    }

    private function loadQuotation(string $noDocument, string $type)
    {
        // QTC => request_quotation_kontrak_H (sering disebut request_quotation_header)
        // QT  => request_quotation
        if ($type === 'QTC') {
            return QuotationKontrakH::where('no_document', $noDocument)
                ->where('is_active', true)
                ->first([
                    'id', 'no_document', 'sales_id', 'nama_perusahaan',
                    'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order',
                    'nama_pic_sampling', 'jabatan_pic_sampling', 'no_tlp_pic_sampling', 'email_pic_sampling',
                ]);
        }

        return QuotationNonKontrak::where('no_document', $noDocument)
            ->where('is_active', true)
            ->first([
                'id', 'no_document', 'sales_id', 'nama_perusahaan',
                'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order',
                'nama_pic_sampling', 'jabatan_pic_sampling', 'no_tlp_pic_sampling', 'email_pic_sampling',
            ]);
    }

    private function parseKategoriItems(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item) {
            return trim((string) $item);
        }, $decoded)));
    }

    private function summarizeKategori(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $parts = explode(' - ', $item, 2);
            $name = trim($parts[0] !== '' ? $parts[0] : $item);
            if ($name === '') {
                continue;
            }
            if (!isset($map[$name])) {
                $map[$name] = 0;
            }
            $map[$name]++;
        }

        $rows = [];
        foreach ($map as $name => $count) {
            $rows[] = [
                'nama' => $name,
                'count' => $count,
                'label' => $name . ' - ' . $count,
            ];
        }

        return $rows;
    }

    private function formatDurasi(int $durasi): string
    {
        if ($durasi === 0) {
            return 'Sesaat';
        }
        if ($durasi === 1) {
            return '8 Jam';
        }
        return ($durasi - 1) . 'x24 Jam';
    }

    private function normalizeName(string $name): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($name)));
    }

    private function resolveRecipients(array $payload, array $testEmails): array
    {
        // Mode test: arahkan ke email custom.
        if ($testEmails !== []) {
            return [
                'to' => $testEmails,
                'cc' => [],
            ];
        }

        // Produksi: kirim HANYA ke PIC Order.
        // Visual email tetap bisa menampilkan PIC Sampling + PIC Order jika berbeda.
        $email = strtolower(trim((string) ($payload['pic_order']['email'] ?? '')));
        $to = ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? [$email] : [];

        return [
            'to' => $to,
            'cc' => [],
        ];
    }

    /**
     * Mode test (--email): cari akun portal customers berdasarkan email tersebut.
     * Mode produksi: cari akun portal dari email PIC quotation.
     *
     * @param  string[]  $emails
     * @return \App\Models\customer\Users[]
     */
    private function resolvePortalUsers(array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        $normalized = array_values(array_unique(array_filter(array_map(function ($email) {
            return strtolower(trim((string) $email));
        }, $emails))));

        if ($normalized === []) {
            return [];
        }

        return PortalUsers::query()
            ->where('is_active', true)
            ->where(function ($query) use ($normalized) {
                foreach ($normalized as $email) {
                    $query->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
                }
            })
            ->get()
            ->all();
    }

    /**
     * @param  \App\Models\customer\Users[]  $portalUsers
     * @param  string[]  $emails
     */
    private function printPortalTargets(array $portalUsers, array $emails): void
    {
        if ($emails === []) {
            $this->line('     PORTAL: (tidak ada email tujuan)');
            return;
        }

        if ($portalUsers === []) {
            $this->warn('     PORTAL: akun tidak ditemukan untuk ' . implode(', ', $emails));
            return;
        }

        $labels = array_map(function ($user) {
            return 'id=' . $user->id . ' <' . $user->email . '>';
        }, $portalUsers);

        $this->line('     PORTAL: ' . implode(', ', $labels));

        $found = array_map(function ($user) {
            return strtolower(trim((string) $user->email));
        }, $portalUsers);
        $missing = array_values(array_diff($emails, $found));
        if ($missing !== []) {
            $this->warn('     PORTAL MISSING: ' . implode(', ', $missing));
        }
    }

    /**
     * @param  \App\Models\customer\Users[]  $portalUsers
     */
    private function sendPortalNotifications(array $portalUsers, array $payload, string $runId): bool
    {
        if ($portalUsers === []) {
            $this->warn('     PORTAL SKIP: tidak ada akun portal yang cocok');
            $this->logger->warning('PORTAL_SKIP_NO_USER', [
                'run_id' => $runId,
                'no_quotation' => $payload['no_quotation'] ?? null,
            ]);
            return false;
        }

        $service = app(PortalNotificationService::class);
        $extra = [
            'no_quotation' => $payload['no_quotation'] ?? null,
            'no_order' => $payload['no_order'] ?? null,
            'nama_perusahaan' => $payload['nama_perusahaan'] ?? null,
            'tanggal_label' => $payload['tanggal_label'] ?? null,
            'jadwal_tanggal' => $payload['jadwal_tanggal'] ?? null,
            'jam_mulai' => $payload['jam_mulai'] ?? null,
        ];

        $anyOk = false;
        foreach ($portalUsers as $user) {
            $extra['customer_name'] = $user->nama_lengkap ?? $user->name ?? $user->nama ?? null;
            $result = $service->send($user->id, 'sampling_h14', $extra);
            $status = $result['status'] ?? 'error';

            if (in_array($status, ['success', 'skipped'], true)) {
                $anyOk = true;
                $expoLabel = !empty($result['expo']) ? 'expo=yes' : 'expo=no';
                $this->info('     PORTAL SENT id=' . $user->id . ' (' . $status . ', ' . $expoLabel . ' → web+mobile)');
                $this->logger->info('PORTAL_SENT', [
                    'run_id' => $runId,
                    'no_quotation' => $payload['no_quotation'] ?? null,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'status' => $status,
                    'expo' => !empty($result['expo']),
                    'message' => $result['message'] ?? null,
                ]);
            } else {
                $this->error('     PORTAL FAILED id=' . $user->id . ': ' . ($result['message'] ?? 'unknown'));
                $this->logger->error('PORTAL_FAILED', [
                    'run_id' => $runId,
                    'no_quotation' => $payload['no_quotation'] ?? null,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'result' => $result,
                ]);
            }
        }

        return $anyOk;
    }

    private function buildSubject(array $payload): string
    {
        $company = trim((string) ($payload['nama_perusahaan'] ?? ''));
        if ($company === '') {
            $company = '-';
        }

        return 'Reminder Sampling ' . $payload['no_quotation'] . ', ' . $company;
    }

    private function buildBody(array $payload): string
    {
        $picRows = [];
        if (!empty($payload['same_pic'])) {
            $pic = ($payload['pic_order']['nama'] ?? '') !== ''
                ? $payload['pic_order']
                : ($payload['pic_sampling'] ?? []);
            if (($pic['nama'] ?? '') !== '') {
                $picRows[] = ['label' => 'PIC', 'pic' => $pic];
            }
        } else {
            if (($payload['pic_sampling']['nama'] ?? '') !== '') {
                $picRows[] = ['label' => 'PIC Sampling', 'pic' => $payload['pic_sampling']];
            }
            if (($payload['pic_order']['nama'] ?? '') !== '') {
                $picRows[] = ['label' => 'PIC Order', 'pic' => $payload['pic_order']];
            }
        }

        return View::make('TemplateEmailJadwal.sampling_h14', [
            'payload' => $payload,
            'picRows' => $picRows,
            'salesName' => $payload['sales']['nama'] ?: '-',
            'salesKontak' => $payload['sales']['kontak'] ?: '-',
            'portal' => 'https://portal.intilab.com/customers',
            'playstore' => env(
                'CUSTOMER_APP_PLAYSTORE_URL',
                'https://play.google.com/store/apps/details?id=com.intilab.mobileppi&hl=id'
            ),
        ])->render();
    }

    private function buildSnapshot(string $targetDate, array $teams): array
    {
        $items = [];
        foreach ($teams as $team) {
            $key = $team['no_quotation'] . '|' . $team['batch_id'];
            $items[$key] = [
                'no_quotation' => $team['no_quotation'],
                'batch_id' => $team['batch_id'],
                'jadwal_tanggal' => $team['jadwal_tanggal'],
                'jam_mulai' => $team['jam_mulai'],
                'durasi' => $team['durasi'],
                'kategori' => $team['kategori'],
                'sampler' => $team['sampler'],
                'kendaraan' => $team['kendaraan'],
                'status' => $team['status'],
                'updated_at' => $team['updated_at'],
                'updated_by' => $team['updated_by'],
                'fingerprint' => sha1(json_encode([
                    $team['no_quotation'],
                    $team['batch_id'],
                    $team['jadwal_tanggal'],
                    $team['jam_mulai'],
                    $team['durasi'],
                    $team['kategori'],
                    $team['sampler'],
                    $team['kendaraan'],
                    $team['status'],
                ], JSON_UNESCAPED_UNICODE)),
            ];
        }

        ksort($items);

        return [
            'target_date' => $targetDate,
            'generated_at' => Carbon::now('Asia/Jakarta')->toDateTimeString(),
            'count' => count($items),
            'items' => $items,
        ];
    }

    private function snapshotDir(): string
    {
        $dir = storage_path('logs/sampling_h14/snapshots');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function latestSnapshotPath(string $targetDate): ?string
    {
        $pattern = $this->snapshotDir() . '/target_' . $targetDate . '_*.json';
        $files = glob($pattern);
        if (!$files) {
            return null;
        }
        rsort($files);
        return $files[0];
    }

    private function diffWithPreviousSnapshot(string $targetDate, array $current): array
    {
        $path = $this->latestSnapshotPath($targetDate);
        if (!$path || !is_file($path)) {
            return ['added' => [], 'removed' => [], 'changed' => [], 'previous' => null];
        }

        $prev = json_decode((string) file_get_contents($path), true);
        if (!is_array($prev) || !isset($prev['items']) || !is_array($prev['items'])) {
            return ['added' => [], 'removed' => [], 'changed' => [], 'previous' => $path];
        }

        $prevItems = $prev['items'];
        $currItems = $current['items'];

        $added = [];
        $removed = [];
        $changed = [];

        foreach ($currItems as $key => $item) {
            if (!isset($prevItems[$key])) {
                $added[] = $key;
                continue;
            }
            if (($prevItems[$key]['fingerprint'] ?? '') !== ($item['fingerprint'] ?? '')) {
                $changed[] = [
                    'key' => $key,
                    'before' => $prevItems[$key],
                    'after' => $item,
                ];
            }
        }

        foreach ($prevItems as $key => $item) {
            if (!isset($currItems[$key])) {
                $removed[] = $key;
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'previous' => $path,
        ];
    }

    private function writeSnapshot(string $targetDate, string $runId, array $snapshot, array $changes): void
    {
        $path = $this->snapshotDir() . '/target_' . $targetDate . '_' . $runId . '.json';
        $payload = $snapshot + [
            'run_id' => $runId,
            'changes_vs_previous' => [
                'previous' => $changes['previous'],
                'added_count' => count($changes['added']),
                'removed_count' => count($changes['removed']),
                'changed_count' => count($changes['changed']),
                'added' => $changes['added'],
                'removed' => $changes['removed'],
                'changed' => $changes['changed'],
            ],
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('Snapshot  : ' . $path);
        $this->logger->info('SNAPSHOT_WRITTEN', [
            'path' => $path,
            'target_date' => $targetDate,
            'count' => $snapshot['count'],
        ]);
    }
}
