<?php

namespace App\Console\Commands;

use App\Models\MasterCabang;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use App\Models\Hr\HrRequest;
use App\Services\Greatday\DynamicApprovalChainService;
use App\Services\Hr\FormAtasanApprovalChainService;
use App\Services\Hr\FormAtasanSubmitNotificationService;
use App\Support\Greatday\KaryawanPresentation;
use Illuminate\Console\Command;
/**
 * Fase 0 — sampel Sampler cabang + atasan ganda (discovery, tidak mengubah data).
 */
class HrSamplerBranchApprovalDiscoverCommand extends Command
{
    protected $signature = 'hr:discover-sampler-branch-approval
                            {--ho-cabang=1 : ID master_cabang untuk HO}
                            {--limit=30 : Maks baris sampel dual-atasan ditampilkan}
                            {--export= : Path file JSON (opsional)}
                            {--chain-for= : Tampilkan chain raw + terfilter untuk satu karyawan_id}';

    protected $description = 'Fase 0: inventaris Sampler cabang, atasan langsung, chain approval (read-only)';

    public function handle(): int
    {
        $chainFor = $this->option('chain-for');
        if (is_string($chainFor) && $chainFor !== '') {
            return $this->printChainForKaryawan((int) $chainFor);
        }

        $hoCabang = (int) $this->option('ho-cabang');
        $limit = max(1, (int) $this->option('limit'));

        $this->info('=== Fase 0: Discovery approval Sampler cabang ===');
        $this->line('HO id_cabang (asumsi): ' . $hoCabang);
        $this->newLine();

        $samplerJabatanIds = MasterJabatan::query()
            ->whereRaw('LOWER(nama_jabatan) LIKE ?', ['%sampler%'])
            ->pluck('id', 'nama_jabatan')
            ->all();

        $this->section('1. Identifikasi jabatan Sampler (master_jabatan)');
        if ($samplerJabatanIds === []) {
            $this->warn('Tidak ada nama_jabatan LIKE %sampler% — fallback ke kolom string master_karyawan.jabatan.');
        } else {
            foreach ($samplerJabatanIds as $name => $id) {
                $this->line("  - [{$id}] {$name}");
            }
        }

        $samplerQuery = MasterKaryawan::query()->where('is_active', 1);
        $samplerQuery->where(function ($q) use ($samplerJabatanIds) {
            $q->whereRaw('LOWER(TRIM(COALESCE(jabatan, ""))) LIKE ?', ['%sampler%']);
            if ($samplerJabatanIds !== []) {
                $q->orWhereIn('id_jabatan', array_values($samplerJabatanIds));
            }
        });

        $allSamplers = (clone $samplerQuery)->get(['id', 'nama_lengkap', 'id_cabang', 'id_jabatan', 'jabatan', 'grade', 'atasan_langsung']);
        $branchSamplers = $allSamplers->filter(fn ($r) => (int) ($r->id_cabang ?? 0) !== $hoCabang && (int) ($r->id_cabang ?? 0) > 0);

        $this->newLine();
        $this->section('2. Ringkasan populasi');
        $this->line('Total karyawan aktif match Sampler: ' . $allSamplers->count());
        $this->line('Sampler penempatan cabang (id_cabang != HO): ' . $branchSamplers->count());
        $this->line('Sampler di HO: ' . $allSamplers->filter(fn ($r) => (int) ($r->id_cabang ?? 0) === $hoCabang)->count());

        $dualBranch = $branchSamplers->filter(function ($row) {
            return count($this->decodeAtasanIds($row)) >= 2;
        });

        $this->line('Sampler cabang dengan >= 2 atasan_langsung: ' . $dualBranch->count());

        $cabangNames = MasterCabang::query()->pluck('nama_cabang', 'id');

        $this->newLine();
        $this->section('3. Distribusi cabang (Sampler cabang, top 15)');
        $byCabang = $branchSamplers->groupBy('id_cabang')->map->count()->sortDesc()->take(15);
        foreach ($byCabang as $cabangId => $count) {
            $label = $cabangNames[$cabangId] ?? ('id=' . $cabangId);
            $this->line("  - {$label}: {$count}");
        }

        $chainService = app(DynamicApprovalChainService::class);
        $samples = [];
        $classCounts = [
            'dual_with_ho_and_same_branch_spv' => 0,
            'dual_without_ho_in_direct' => 0,
            'chain_has_branch_spv_first' => 0,
        ];

        $this->newLine();
        $this->section('4. Sampel dual-atasan (max ' . $limit . ')');

        $rows = $dualBranch->take($limit);
        $tableRows = [];

        foreach ($dualBranch as $row) {
            $directIds = $this->decodeAtasanIds($row);
            $submitterCabang = (int) ($row->id_cabang ?? 0);
            $directMeta = $this->loadAtasanMeta($directIds, $cabangNames, $hoCabang, $submitterCabang);
            $chainIds = $chainService->orderedApproverIds($row);
            $chainMeta = $this->loadAtasanMeta($chainIds, $cabangNames, $hoCabang, $submitterCabang);

            $hasHoDirect = collect($directMeta)->contains(fn ($a) => $a['cabang_class'] === 'HO');
            $hasSameBranchSpv = collect($directMeta)->contains(function ($a) use ($row) {
                return $a['cabang_class'] === 'SAME_BRANCH' && $this->normalizeGrade($a['grade']) === 'SUPERVISOR';
            });

            if (count($directIds) >= 2 && $hasHoDirect && $hasSameBranchSpv) {
                $classCounts['dual_with_ho_and_same_branch_spv']++;
            }
            if (count($directIds) >= 2 && !$hasHoDirect) {
                $classCounts['dual_without_ho_in_direct']++;
            }
            if ($chainMeta !== [] && ($chainMeta[0]['cabang_class'] ?? '') === 'SAME_BRANCH') {
                $classCounts['chain_has_branch_spv_first']++;
            }
        }

        foreach ($rows as $row) {
            $submitterCabang = (int) ($row->id_cabang ?? 0);
            $directIds = $this->decodeAtasanIds($row);
            $directMeta = $this->loadAtasanMeta($directIds, $cabangNames, $hoCabang, $submitterCabang);
            $chainIds = $chainService->orderedApproverIds($row);
            $chainMeta = $this->loadAtasanMeta($chainIds, $cabangNames, $hoCabang, $submitterCabang);

            $cabangLabel = $cabangNames[(int) $row->id_cabang] ?? ('id=' . $row->id_cabang);
            $jabatanLabel = KaryawanPresentation::jabatanLabel($row) ?: ($row->jabatan ?? '-');

            $tableRows[] = [
                $row->id,
                mb_substr($row->nama_lengkap ?? '', 0, 28),
                $cabangLabel,
                $jabatanLabel,
                count($directIds),
                $this->formatAtasanBrief($directMeta),
                $this->formatAtasanBrief($chainMeta),
            ];

            $samples[] = [
                'karyawan_id' => (int) $row->id,
                'nama' => $row->nama_lengkap,
                'id_cabang' => (int) $row->id_cabang,
                'cabang' => $cabangLabel,
                'jabatan' => $jabatanLabel,
                'grade' => $row->grade,
                'atasan_langsung' => $directIds,
                'atasan_langsung_detail' => $directMeta,
                'chain_ordered_approver_ids' => $chainIds,
                'chain_detail' => $chainMeta,
            ];
        }

        if ($tableRows === []) {
            $this->warn('Tidak ada Sampler cabang dengan >= 2 atasan langsung.');
        } else {
            $this->table(
                ['ID', 'Nama', 'Cabang', 'Jabatan', '#Atasan', 'Atasan langsung', 'Chain approve (DAC)'],
                $tableRows
            );
        }

        $this->newLine();
        $this->section('5. Pola (seluruh Sampler cabang dual-atasan, bukan hanya sampel)');
        foreach ($classCounts as $key => $count) {
            $this->line("  - {$key}: {$count}");
        }

        $this->newLine();
        $this->section('6. Draft rule filter (untuk sign-off HR — belum diterapkan)');
        $this->line('  KEEP approver: id_cabang = HO (' . $hoCabang . ') OR label teknis (TBD)');
        $this->line('  DROP: SPV cabang operasional + seluruh hirarki di atasnya (BFS hanya dari atasan HO/teknis)');
        $this->line('  Scope: pengajuan BARU cuti/izin/koreksi absen; data lama tidak diubah.');

        $exportPath = $this->option('export');
        if (is_string($exportPath) && $exportPath !== '') {
            $payload = [
                'generated_at' => date('c'),
                'ho_cabang_id' => $hoCabang,
                'sampler_jabatan_ids' => $samplerJabatanIds,
                'summary' => [
                    'total_sampler_active' => $allSamplers->count(),
                    'sampler_branch' => $branchSamplers->count(),
                    'sampler_branch_dual_atasan' => $dualBranch->count(),
                    'patterns' => $classCounts,
                ],
                'samples' => $samples,
            ];
            file_put_contents($exportPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('JSON export: ' . $exportPath);
        }

        $this->newLine();
        $this->info('Discovery selesai (read-only).');

        return 0;
    }

    private function printChainForKaryawan(int $karyawanId): int
    {
        $row = MasterKaryawan::query()->where('is_active', 1)->find($karyawanId);
        if (!$row) {
            $this->error('Karyawan id=' . $karyawanId . ' tidak ditemukan atau tidak aktif.');

            return 1;
        }

        $hoCabang = (int) $this->option('ho-cabang');
        $cabangNames = MasterCabang::query()->pluck('nama_cabang', 'id')->all();
        $submitterCabang = (int) ($row->id_cabang ?? 0);
        $cabangLabel = $cabangNames[$submitterCabang] ?? ('id=' . $submitterCabang);

        $this->info('Karyawan: ' . ($row->nama_lengkap ?? '-') . ' (id=' . $row->id . ')');
        $this->line('Cabang: ' . $cabangLabel . ' | Jabatan: ' . (KaryawanPresentation::jabatanLabel($row) ?: ($row->jabatan ?? '-')));
        $this->line('HR_SAMPLER_BRANCH_APPROVAL_FILTER: '
            . (config('greatday.sampler_branch_approval_filter') ? 'true' : 'false'));
        $this->newLine();

        $directIds = $this->decodeAtasanIds($row);
        $directMeta = $this->loadAtasanMeta($directIds, $cabangNames, $hoCabang, $submitterCabang);
        $this->line('Atasan langsung: ' . $this->formatAtasanBrief($directMeta));
        $this->newLine();

        $rawIds = app(DynamicApprovalChainService::class)->orderedApproverIds($row);
        $rawMeta = $this->loadAtasanMeta($rawIds, $cabangNames, $hoCabang, $submitterCabang);
        $this->section('Chain RAW (DynamicApprovalChainService)');
        $this->printChainLines($rawMeta);

        $chainService = app(FormAtasanApprovalChainService::class);
        $notify = app(FormAtasanSubmitNotificationService::class);
        $scoped = [
            'cuti' => HrRequest::TYPE_LEAVE,
            'izin' => HrRequest::TYPE_PERMISSION,
            'koreksi absen' => HrRequest::TYPE_ATTENDANCE_CORRECTION,
            'lembur (tanpa filter Sampler)' => HrRequest::TYPE_OVERTIME,
        ];

        foreach ($scoped as $label => $type) {
            $filteredIds = $chainService->orderedApproverIds($row, $type);
            $filteredMeta = $this->loadAtasanMeta($filteredIds, $cabangNames, $hoCabang, $submitterCabang);
            $first = $notify->initialApproverIds($row, $type);
            $this->newLine();
            $this->section('Chain terfilter — ' . $label);
            if ($filteredMeta === []) {
                $this->warn('  (kosong)');
            } else {
                $this->printChainLines($filteredMeta);
            }
            $this->line('Notifikasi submit (approver pertama): ' . ($first !== [] ? implode(', ', $first) : '-'));
        }

        return 0;
    }

    /** @param list<array<string, mixed>> $meta */
    private function printChainLines(array $meta): void
    {
        $order = 1;
        foreach ($meta as $a) {
            $this->line(sprintf(
                '  %d. [%s] %s — cabang_id=%s %s (%s)',
                $order++,
                $a['id'] ?? '?',
                $a['nama'] ?? '?',
                $a['id_cabang'] ?? '?',
                $a['cabang_label'] ?? '?',
                $a['grade'] ?? '?'
            ));
        }
    }

    /** @return list<int> */
    private function decodeAtasanIds(MasterKaryawan $employee): array
    {
        $raw = $employee->atasan_langsung ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $decoded), fn ($id) => $id > 0));
    }

    /**
     * @param list<int> $ids
     * @param \Illuminate\Support\Collection<int, string> $cabangNames
     * @return list<array<string, mixed>>
     */
    private function loadAtasanMeta(array $ids, $cabangNames, int $hoCabang, ?int $submitterCabang = null): array
    {
        if ($ids === []) {
            return [];
        }

        $people = MasterKaryawan::query()
            ->whereIn('id', $ids)
            ->where('is_active', 1)
            ->get(['id', 'nama_lengkap', 'id_cabang', 'grade', 'jabatan', 'id_jabatan', 'id_department']);

        $byId = $people->keyBy('id');
        $out = [];
        foreach ($ids as $id) {
            $p = $byId->get($id);
            if (!$p) {
                $out[] = ['id' => $id, 'nama' => '?', 'cabang_class' => 'MISSING'];
                continue;
            }
            $cabangId = (int) ($p->id_cabang ?? 0);
            $out[] = [
                'id' => (int) $p->id,
                'nama' => $p->nama_lengkap,
                'grade' => $p->grade,
                'jabatan' => KaryawanPresentation::jabatanLabel($p) ?: $p->jabatan,
                'id_cabang' => $cabangId,
                'cabang' => $cabangNames[$cabangId] ?? ('id=' . $cabangId),
                'cabang_class' => $this->classifyCabang($cabangId, $hoCabang, $submitterCabang),
            ];
        }

        return $out;
    }

    private function classifyCabang(int $approverCabang, int $hoCabang, ?int $submitterCabang): string
    {
        if ($approverCabang === $hoCabang) {
            return 'HO';
        }
        if ($submitterCabang !== null && $approverCabang === $submitterCabang) {
            return 'SAME_BRANCH';
        }
        if ($submitterCabang === null) {
            return 'NON_HO';
        }

        return 'OTHER_BRANCH';
    }

    private function formatAtasanBrief(array $meta): string
    {
        if ($meta === []) {
            return '-';
        }
        $parts = [];
        foreach ($meta as $a) {
            $g = $this->normalizeGrade($a['grade'] ?? '');
            $parts[] = ($a['nama'] ?? '?') . ' [' . ($a['cabang_class'] ?? '?') . '/' . $g . ']';
        }

        return mb_substr(implode('; ', $parts), 0, 80);
    }

    private function normalizeGrade(?string $grade): string
    {
        $g = strtoupper(trim(str_replace('_', ' ', (string) $grade)));
        if ($g === 'SPV') {
            return 'SUPERVISOR';
        }

        return $g;
    }

    private function section(string $title): void
    {
        $this->line('<comment>' . $title . '</comment>');
    }
}
