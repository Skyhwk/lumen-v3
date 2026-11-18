<?php

namespace App\Services;

use App\Models\BeritaAcaraKejadianDisipliner;
use App\Models\MasterKaryawan;
use App\Services\Greatday\DynamicApprovalChainService;
use Carbon\Carbon;

final class BakAtasanWorkflowService
{
    /**
     * @return list<array{karyawan_id: int, nama: string, grade: string, state: string, acted_at: ?string, actor_name: ?string}>
     */
    public function buildApprovalChain(MasterKaryawan $submitter): array
    {
        $approverIds = app(DynamicApprovalChainService::class)->orderedApproverIdsForBak($submitter);
        $chain = [];

        foreach ($approverIds as $approverId) {
            $approverId = (int) $approverId;
            if ($approverId <= 0 || $approverId === (int) $submitter->id) {
                continue;
            }

            $person = MasterKaryawan::query()
                ->where('id', $approverId)
                ->where('is_active', true)
                ->first(['id', 'nama_lengkap', 'grade']);

            if (!$person) {
                continue;
            }

            $chain[] = [
                'karyawan_id' => (int) $person->id,
                'nama' => trim((string) ($person->nama_lengkap ?? '')) ?: '-',
                'grade' => strtoupper(trim((string) ($person->grade ?? ''))),
                'state' => 'pending',
                'acted_at' => null,
                'actor_name' => null,
            ];
        }

        return $chain;
    }

    public function seedVerification(BeritaAcaraKejadianDisipliner $document): BeritaAcaraKejadianDisipliner
    {
        $submitter = MasterKaryawan::query()
            ->where('id', (int) $document->penandatangan_karyawan_id)
            ->where('is_active', true)
            ->first();

        if (!$submitter) {
            throw new \RuntimeException('Data penandatangan tidak ditemukan.');
        }

        $chain = $this->buildApprovalChain($submitter);

        if ($chain === []) {
            $document->proses_status = BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES;
            $document->approval_step = 0;
            $document->approval_chain = null;
        } else {
            $document->proses_status = BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN;
            $document->approval_step = 0;
            $document->approval_chain = json_encode($chain, JSON_UNESCAPED_UNICODE);
        }

        return $document;
    }

    public function currentPendingApproverId(BeritaAcaraKejadianDisipliner $document): ?int
    {
        $chain = $this->decodeChain($document->approval_chain);
        $step = (int) $document->approval_step;

        if ($document->proses_status !== BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN) {
            return null;
        }

        if (!isset($chain[$step])) {
            return null;
        }

        $item = $chain[$step];
        if (($item['state'] ?? '') !== 'pending') {
            return null;
        }

        $id = (int) ($item['karyawan_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public function viewerCanApproveAtasan(BeritaAcaraKejadianDisipliner $document, int $viewerKaryawanId): bool
    {
        $expected = $this->currentPendingApproverId($document);

        return $expected !== null && $expected === $viewerKaryawanId;
    }

  /**
     * @return array{done: bool, document: BeritaAcaraKejadianDisipliner}
     */
    public function markAtasanApproved(BeritaAcaraKejadianDisipliner $document, MasterKaryawan $approver): array
    {
        if (!$this->viewerCanApproveAtasan($document, (int) $approver->id)) {
            throw new \RuntimeException('Anda bukan verifikator pada tahap ini.');
        }

        $chain = $this->decodeChain($document->approval_chain);
        $step = (int) $document->approval_step;
        $now = Carbon::now()->toDateTimeString();

        $chain[$step]['state'] = 'approved';
        $chain[$step]['acted_at'] = $now;
        $chain[$step]['actor_name'] = trim((string) ($approver->nama_lengkap ?? '')) ?: '-';

        $log = $this->decodeLog($document->approval_log);
        $log[] = [
            'action' => 'approve_atasan',
            'step' => $step,
            'karyawan_id' => (int) $approver->id,
            'nama' => $chain[$step]['actor_name'],
            'at' => $now,
        ];

        $nextStep = $step + 1;
        $done = !isset($chain[$nextStep]);

        $document->approval_chain = json_encode($chain, JSON_UNESCAPED_UNICODE);
        $document->approval_log = json_encode($log, JSON_UNESCAPED_UNICODE);
        $document->approval_step = $done ? $step : $nextStep;

        if ($done) {
            $document->proses_status = BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES;
        }

        return ['done' => $done, 'document' => $document];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function decodeChain($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function decodeLog($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? $decoded : [];
    }

    public function canEditProses(BeritaAcaraKejadianDisipliner $document): bool
    {
        return $document->proses_status !== BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES
            && (bool) $document->is_active;
    }

    public function buildAjukanSuccessMessage(BeritaAcaraKejadianDisipliner $document): string
    {
        $chain = $this->decodeChain($document->approval_chain);

        if ($document->proses_status !== BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN || $chain === []) {
            return 'BAK diajukan. Tidak ada Manager/Senior Manager pada hirarki penandatangan — langsung masuk antrian menunggu diproses.';
        }

        $steps = [];
        foreach ($chain as $index => $item) {
            $nama = trim((string) ($item['nama'] ?? '')) ?: '-';
            $grade = $this->formatGradeShortLabel((string) ($item['grade'] ?? ''));
            $steps[] = ($index + 1) . '. ' . $nama . ' (' . $grade . ')';
        }

        $first = $chain[0];
        $menunggu = trim((string) ($first['nama'] ?? '')) ?: '-';
        $menungguGrade = $this->formatGradeShortLabel((string) ($first['grade'] ?? ''));
        $jumlah = count($chain);
        $tahapLabel = $jumlah === 1 ? '1 tahap' : $jumlah . ' tahap';

        return 'BAK diajukan. Verifikasi atasan ' . $tahapLabel
            . ' (Manager → Senior Manager, tanpa SPV): '
            . implode('; ', $steps)
            . '. Menunggu persetujuan tahap 1: ' . $menunggu . ' (' . $menungguGrade . ').';
    }

    private function formatGradeShortLabel(string $grade): string
    {
        $g = strtoupper(trim(str_replace('_', ' ', $grade)));
        if ($g === 'SPV' || $g === 'SUPERVISOR' || str_starts_with($g, 'SUPERVISOR')) {
            return 'SPV';
        }
        if ($g === 'MANAGER' || str_starts_with($g, 'MANAGER')) {
            return 'Manager';
        }
        if ($g === 'SENIOR MANAGER' || str_starts_with($g, 'SENIOR MANAGER')) {
            return 'Senior Manager';
        }

        return $g !== '' ? $g : '-';
    }
}