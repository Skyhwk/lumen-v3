<?php

namespace App\Services;

use App\Models\BeritaAcaraKejadianDisipliner;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat BAK (buat → verifikasi atasan → diproses) untuk digabungkan ke timeline Investigasi Awal.
 */
final class BakKejadianTimelineService
{
    /** @var array<string, string> */
    private const PROSES_LABELS = [
        BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN => 'Permintaan Dokumen',
        BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN => 'Verifikasi Atasan',
        BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES => 'Menunggu Diproses',
        BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES => 'Sedang Diproses',
        BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES => 'Sudah Diproses',
    ];

    /**
     * @param  list<int>  $bakIds
     * @return list<array<string, mixed>>
     */
    public function buildForBakIds(array $bakIds, int $investigasiId = 0): array
    {
        $bakIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $bakIds),
            static fn ($id) => $id > 0
        )));

        if ($bakIds === [] || !Schema::hasTable('berita_acara_kejadian_disipliner')) {
            return [];
        }

        $workflow = app(BakAtasanWorkflowService::class);

        $rows = DB::table('berita_acara_kejadian_disipliner')
            ->whereIn('id', $bakIds)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $linkAtByBakId = $this->loadLinkTimestamps($investigasiId, $bakIds);
        $invDocLabel = $this->resolveInvestigasiDocLabel($investigasiId);

        $entries = [];
        foreach ($rows as $row) {
            $bakId = (int) $row->id;
            $nomor = trim((string) ($row->nomor_surat ?? '')) ?: ('#' . $bakId);
            $prefix = $nomor;

            $chain = $workflow->decodeChain($row->approval_chain ?? null);
            $approvalLog = $workflow->decodeLog($row->approval_log ?? null);
            $proses = (string) ($row->proses_status ?? '');

            $entries[] = $this->entry(
                "bak-{$bakId}-create",
                'bak_create',
                'BAK Dibuat',
                null,
                self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN] ?? 'Permintaan Dokumen',
                trim((string) ($row->created_by ?? '')) ?: '-',
                '',
                $row->created_at
            );

            $ajukanMeta = $this->resolveAjukanMeta($row, $chain, $approvalLog, $proses);
            if ($ajukanMeta !== null) {
                [$ajukanAt, $toProses] = $ajukanMeta;
                $entries[] = $this->entry(
                    "bak-{$bakId}-ajukan",
                    'bak_submit',
                    'BAK Diajukan',
                    self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN] ?? 'Permintaan Dokumen',
                    $toProses,
                    trim((string) ($row->created_by ?? '')) ?: '-',
                    '',
                    $ajukanAt
                );
            }

            foreach ($approvalLog as $index => $logItem) {
                $action = (string) ($logItem['action'] ?? '');
                $at = $logItem['at'] ?? null;
                if ($at === null || $at === '') {
                    continue;
                }

                $step = (int) ($logItem['step'] ?? 0);
                $actor = trim((string) ($logItem['nama'] ?? '')) ?: '-';
                $tahap = $step + 1;

                if ($action === 'approve_atasan') {
                    $isLastInLog = $this->isLastApprovalStep($chain, $step, $proses);
                    $entries[] = $this->entry(
                        "bak-{$bakId}-approve-{$index}",
                        'bak_approve_atasan',
                        'Verifikasi Atasan Disetujui (Tahap ' . $tahap . ')',
                        self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN] ?? 'Verifikasi Atasan',
                        $isLastInLog
                            ? (self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES] ?? 'Menunggu Diproses')
                            : (self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN] ?? 'Verifikasi Atasan'),
                        $actor,
                        '',
                        $at
                    );
                } elseif ($action === 'reject_atasan') {
                    $reason = trim((string) ($logItem['reason'] ?? ''));
                    $entries[] = $this->entry(
                        "bak-{$bakId}-reject-{$index}",
                        'bak_reject_atasan',
                        'Verifikasi Atasan Ditolak (Tahap ' . $tahap . ')',
                        self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN] ?? 'Verifikasi Atasan',
                        self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN] ?? 'Permintaan Dokumen',
                        $actor,
                        $reason,
                        $at,
                        'rejected'
                    );
                }
            }

            $linkedAt = $linkAtByBakId[$bakId] ?? null;
            $linkedToInvestigasi = ($linkedAt !== null && $linkedAt !== '')
                || $investigasiId > 0;

            /*
             * Status proses_bak (sedang/sudah diproses) sering di-set otomatis lewat sync investigasi
             * (updated_at = saat F1 terbit). Jangan ditampilkan sebagai langkah terpisah setelah Terbit.
             * Hanya sintesis untuk BAK yang diproses manual tanpa investigasi F1.
             */
            if (!$linkedToInvestigasi) {
                $milestoneAt = $this->inferLastBakWorkflowAt($row, $approvalLog, $chain);
                if ($proses === BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES) {
                    $entries[] = $this->entry(
                        "bak-{$bakId}-proses",
                        'bak_mulai_proses',
                        'BAK Sedang Diproses',
                        self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES] ?? 'Menunggu Diproses',
                        self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES] ?? 'Sedang Diproses',
                        trim((string) ($row->updated_by ?? '')) ?: '-',
                        '',
                        $milestoneAt
                    );
                } elseif ($proses === BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES) {
                    $entries[] = $this->entry(
                        "bak-{$bakId}-selesai",
                        'bak_selesai_proses',
                        'BAK Sudah Diproses',
                        self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES] ?? 'Sedang Diproses',
                        self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES] ?? 'Sudah Diproses',
                        trim((string) ($row->updated_by ?? '')) ?: '-',
                        '',
                        $milestoneAt
                    );
                }
            }

            if ($linkedAt !== null && $linkedAt !== '') {
                $linkInvId = $investigasiId > 0
                    ? $investigasiId
                    : $this->resolveInvestigasiIdForBak($bakId);
                $linkDocNo = $linkInvId > 0
                    ? $this->resolveInvestigasiDocLabel($linkInvId)
                    : $invDocLabel;
                $invToLabel = $linkDocNo !== ''
                    ? 'Investigasi ' . $linkDocNo
                    : 'Investigasi Awal (F1)';
                $entries[] = $this->entry(
                    "bak-{$bakId}-link-inv",
                    'bak_link_investigasi',
                    'BAK Ditautkan ke Investigasi',
                    self::PROSES_LABELS[$proses] ?? ucfirst(str_replace('_', ' ', $proses)),
                    $invToLabel,
                    $this->resolveInvestigasiCreatorName($linkInvId),
                    '',
                    $linkedAt
                );
            }
        }

        return $this->sortEntries($entries);
    }

    /**
     * Timeline lengkap satu BAK + investigasi terkait (jika sudah ditautkan).
     *
     * @return list<array<string, mixed>>
     */
    public function buildMergedForBakView(int $bakId): array
    {
        if ($bakId <= 0) {
            return [];
        }

        $investigasiId = $this->resolveInvestigasiIdForBak($bakId);
        $bakLogs = $this->buildForBakIds([$bakId], $investigasiId);
        $invLogs = $this->loadInvestigasiStatusLogs($investigasiId);

        return self::mergeTimelineEntries($bakLogs, $invLogs);
    }

    /**
     * Gabungkan entri BAK + Investigasi lalu urutkan waktu.
     *
     * @param  list<array<string, mixed>>  $bakEntries
     * @param  list<array<string, mixed>>  $invEntries
     * @return list<array<string, mixed>>
     */
    public static function mergeTimelineEntries(array $bakEntries, array $invEntries): array
    {
        $merged = array_merge($bakEntries, $invEntries);

        usort($merged, static function (array $a, array $b) {
            $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }

            $sa = ($a['timeline_source'] ?? '') === 'bak' ? 0 : 1;
            $sb = ($b['timeline_source'] ?? '') === 'bak' ? 0 : 1;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });

        return array_values($merged);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildForInvestigasi(int $investigasiId): array
    {
        if ($investigasiId <= 0 || !Schema::hasTable('bak_investigasi_berita_acara')) {
            return [];
        }

        $bakIds = DB::table('bak_investigasi_berita_acara')
            ->where('bak_investigasi_id', $investigasiId)
            ->pluck('berita_acara_id')
            ->map(static fn ($v) => (int) $v)
            ->filter(static fn ($v) => $v > 0)
            ->values()
            ->all();

        return $this->buildForBakIds($bakIds, $investigasiId);
    }

    /**
     * @param  list<array<string, mixed>>  $chain
     * @param  list<array<string, mixed>>  $approvalLog
     * @return array{0: string, 1: string}|null
     */
    private function resolveAjukanMeta($row, array $chain, array $approvalLog, string $proses): ?array
    {
        $stillPermintaan = $proses === BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN;
        if ($stillPermintaan && $chain === [] && $approvalLog === []) {
            return null;
        }

        $ajukanAt = null;
        if ($approvalLog !== []) {
            $firstAt = $approvalLog[0]['at'] ?? null;
            if ($firstAt) {
                $ajukanAt = Carbon::parse($firstAt)->subSecond()->toDateTimeString();
            }
        }

        if ($ajukanAt === null && $chain !== []) {
            foreach ($chain as $item) {
                $acted = $item['acted_at'] ?? null;
                if ($acted) {
                    $ajukanAt = Carbon::parse($acted)->subSecond()->toDateTimeString();
                    break;
                }
            }
        }

        if ($ajukanAt === null) {
            $ajukanAt = (string) ($row->updated_at ?? $row->created_at ?? date('Y-m-d H:i:s'));
        }

        if ($chain === []) {
            $to = self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES] ?? 'Menunggu Diproses';

            return [$ajukanAt, $to];
        }

        $to = self::PROSES_LABELS[BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN] ?? 'Verifikasi Atasan';

        return [$ajukanAt, $to];
    }

    /**
     * @param  list<array<string, mixed>>  $chain
     */
    private function isLastApprovalStep(array $chain, int $step, string $proses): bool
    {
        if ($proses !== BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN) {
            return true;
        }

        return !isset($chain[$step + 1]);
    }

    /**
     * @param  list<int>  $bakIds
     * @return array<int, string>
     */
    private function loadLinkTimestamps(int $investigasiId, array $bakIds): array
    {
        if ($investigasiId <= 0 || $bakIds === [] || !Schema::hasTable('bak_investigasi_berita_acara')) {
            return [];
        }

        return DB::table('bak_investigasi_berita_acara')
            ->where('bak_investigasi_id', $investigasiId)
            ->whereIn('berita_acara_id', $bakIds)
            ->pluck('created_at', 'berita_acara_id')
            ->mapWithKeys(static fn ($at, $bakId) => [(int) $bakId => (string) $at])
            ->all();
    }

    /**
     * Perkiraan waktu terakhir alur BAK (tanpa memakai updated_at yang bisa berubah saat sync F1).
     *
     * @param  list<array<string, mixed>>  $approvalLog
     * @param  list<array<string, mixed>>  $chain
     */
    private function inferLastBakWorkflowAt($row, array $approvalLog, array $chain): string
    {
        $candidates = [];

        foreach ($approvalLog as $item) {
            $at = $item['at'] ?? null;
            if ($at !== null && $at !== '') {
                $candidates[] = (string) $at;
            }
        }

        foreach ($chain as $item) {
            $acted = $item['acted_at'] ?? null;
            if ($acted !== null && $acted !== '') {
                $candidates[] = (string) $acted;
            }
        }

        if (!empty($row->created_at)) {
            $candidates[] = (string) $row->created_at;
        }

        if ($candidates === []) {
            return (string) ($row->created_at ?? date('Y-m-d H:i:s'));
        }

        usort($candidates, static fn ($a, $b) => strcmp($a, $b));

        return $candidates[count($candidates) - 1];
    }

    private function resolveInvestigasiIdForBak(int $bakId): int
    {
        if ($bakId <= 0 || !Schema::hasTable('bak_investigasi_berita_acara')) {
            return 0;
        }

        return (int) DB::table('bak_investigasi_berita_acara')
            ->where('berita_acara_id', $bakId)
            ->value('bak_investigasi_id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadInvestigasiStatusLogs(int $investigasiId): array
    {
        if ($investigasiId <= 0 || !Schema::hasTable('bak_investigasi_status_logs')) {
            return [];
        }

        $logs = DB::table('bak_investigasi_status_logs')
            ->where('bak_investigasi_id', $investigasiId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $formatted = InvestigasiAwalWorkflowService::formatLogs($logs);
        foreach ($formatted as &$item) {
            $item['timeline_source'] = 'investigasi';
        }
        unset($item);

        return $formatted;
    }

    private function resolveInvestigasiDocLabel(int $investigasiId): string
    {
        if ($investigasiId <= 0 || !Schema::hasTable('bak_investigasi')) {
            return '';
        }

        $no = trim((string) DB::table('bak_investigasi')->where('id', $investigasiId)->value('no_document'));

        return $no;
    }

    private function resolveInvestigasiCreatorName(int $investigasiId): string
    {
        if ($investigasiId <= 0 || !Schema::hasTable('bak_investigasi')) {
            return '-';
        }

        $row = DB::table('bak_investigasi')
            ->where('id', $investigasiId)
            ->first(['created_by', 'creator_karyawan_id']);

        if ($row === null) {
            return '-';
        }

        $name = trim((string) ($row->created_by ?? ''));
        if ($name === '' && (int) ($row->creator_karyawan_id ?? 0) > 0 && Schema::hasTable('master_karyawan')) {
            $name = trim((string) DB::table('master_karyawan')
                ->where('id', (int) $row->creator_karyawan_id)
                ->value('nama_lengkap'));
        }

        return $name !== '' ? $name : '-';
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(
        string $id,
        string $action,
        string $actionLabel,
        ?string $fromLabel,
        string $toLabel,
        string $actorName,
        string $notes,
        $createdAt,
        ?string $toStatus = null
    ): array {
        return [
            'id' => $id,
            'from_status' => null,
            'to_status' => $toStatus ?? 'bak',
            'from_status_label' => $fromLabel,
            'to_status_label' => $toLabel,
            'transition_label' => $this->humanTransitionLabel($fromLabel, $toLabel, $action),
            'action' => $action,
            'action_label' => $actionLabel,
            'actor_name' => $actorName !== '' ? $actorName : '-',
            'notes' => $notes,
            'created_at' => $createdAt,
            'timeline_source' => 'bak',
        ];
    }

    private function humanTransitionLabel(?string $fromLabel, string $toLabel, string $action): ?string
    {
        $from = $fromLabel !== null ? trim($fromLabel) : '';
        $to = trim($toLabel);

        if ($action === 'bak_create' && $to !== '') {
            return 'Status: ' . $to;
        }

        if ($from !== '' && $to !== '' && $from !== $to) {
            return $from . ' → ' . $to;
        }

        if ($to !== '') {
            return 'Status: ' . $to;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function sortEntries(array $entries): array
    {
        usort($entries, static function (array $a, array $b) {
            $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }

            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });

        return $entries;
    }
}