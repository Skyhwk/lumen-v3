<?php

namespace App\Services;

use App\Models\BeritaAcaraKejadianDisipliner;
use App\Models\MasterKaryawan;
use App\Services\BakLaporanService;
use App\Support\InvestigasiAwalStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class InvestigasiPengesahanService
{
    public const SLOT_DISUSUN = 'disusun_oleh';

    public const SLOT_DISETUJUI = 'disetujui_oleh';

    public const SLOT_DIKETAHUI = 'diketahui_oleh';

    public function ensurePengesahanShell(int $investigasiId): void
    {
        if (!Schema::hasTable('bak_investigasi_pengesahan')) {
            return;
        }

        $exists = DB::table('bak_investigasi_pengesahan')
            ->where('bak_investigasi_id', $investigasiId)
            ->exists();

        if ($exists) {
            return;
        }

        $now = Carbon::now();
        $slots = [
            ['urutan' => 1, 'keterangan' => self::SLOT_DISUSUN],
            ['urutan' => 2, 'keterangan' => self::SLOT_DISETUJUI],
            ['urutan' => 3, 'keterangan' => self::SLOT_DIKETAHUI],
            ['urutan' => 4, 'keterangan' => self::SLOT_DIKETAHUI],
        ];

        foreach ($slots as $row) {
            DB::table('bak_investigasi_pengesahan')->insert([
                'bak_investigasi_id' => $investigasiId,
                'urutan' => $row['urutan'],
                'keterangan' => $row['keterangan'],
                'state' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function approveDisusun(int $investigasiId, MasterKaryawan $actor): void
    {
        $this->ensurePengesahanShell($investigasiId);
        $jabatan = $this->resolveJabatan($actor);
        $now = Carbon::now();

        DB::table('bak_investigasi_pengesahan')
            ->where('bak_investigasi_id', $investigasiId)
            ->where('urutan', 1)
            ->update([
                'nama' => $actor->nama_lengkap,
                'jabatan' => $jabatan,
                'tanggal' => $now->toDateString(),
                'karyawan_id' => $actor->id,
                'state' => 'approved',
                'acted_at' => $now,
                'actor_name' => $actor->nama_lengkap,
                'updated_at' => $now,
            ]);
    }

    /**
     * @param  array<string, mixed>  $g1
     */
    public function approveLegal(
        int $investigasiId,
        MasterKaryawan $legalActor,
        array $g1
    ): void {
        BakInvestigasiService::applyG1Fields($investigasiId, $g1);

        $this->ensurePengesahanShell($investigasiId);
        $now = Carbon::now();
        $jabatan = $this->resolveJabatan($legalActor);

        DB::table('bak_investigasi_pengesahan')
            ->where('bak_investigasi_id', $investigasiId)
            ->where('urutan', 2)
            ->update([
                'nama' => $legalActor->nama_lengkap,
                'jabatan' => $jabatan ?: 'Legal & Disciplinary Manager',
                'tanggal' => $now->toDateString(),
                'karyawan_id' => $legalActor->id,
                'state' => 'approved',
                'acted_at' => $now,
                'actor_name' => $legalActor->nama_lengkap,
                'updated_at' => $now,
            ]);

        $lanes = $this->buildDiketahuiLanes($investigasiId);
        $this->seedDiketahuiRows($investigasiId, $lanes);

        // Dokumen F1 selesai setelah Legal menyetujui G.1 (tanda tangan Diketahui hanya di PDF).
        DB::table('bak_investigasi')
            ->where('id', $investigasiId)
            ->update([
                'approval_chain' => json_encode($lanes, JSON_UNESCAPED_UNICODE),
                'legal_approved_at' => $now,
                'legal_approved_by' => $legalActor->nama_lengkap,
                'status' => InvestigasiAwalStatus::ISSUED,
                'published_at' => $now,
                'published_by' => $legalActor->nama_lengkap,
                'updated_at' => $now,
            ]);

        BakLaporanService::syncBeritaAcaraProsesForInvestigasi($investigasiId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildDiketahuiLanes(int $investigasiId): array
    {
        $bakIds = $this->linkedBakIds($investigasiId);
        if ($bakIds === []) {
            return [];
        }

        $baks = BeritaAcaraKejadianDisipliner::query()
            ->whereIn('id', $bakIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'created_by', 'penandatangan_karyawan_id']);

        $seenReporter = [];
        $lanes = [];
        $chainService = app(BakApprovalChainService::class);

        foreach ($baks as $bak) {
            $submitter = $this->resolveSubmitterForBak($bak);
            if ($submitter === null) {
                continue;
            }

            $reporterKey = mb_strtolower(trim((string) $submitter->nama_lengkap));
            if ($reporterKey === '' || isset($seenReporter[$reporterKey])) {
                continue;
            }
            $seenReporter[$reporterKey] = true;

            $approverIds = $chainService->orderedApproverIds($submitter);
            $approverId = $approverIds[0] ?? null;
            if ($approverId === null) {
                continue;
            }

            $approver = MasterKaryawan::query()
                ->where('id', $approverId)
                ->where('is_active', true)
                ->first(['id', 'nama_lengkap', 'grade', 'department']);

            if ($approver === null) {
                continue;
            }

            $lanes[] = [
                'slot' => 'diketahui_' . (count($lanes) + 1),
                'berita_acara_id' => (int) $bak->id,
                'karyawan_id' => (int) $approver->id,
                'nama' => trim((string) $approver->nama_lengkap),
                'jabatan' => $this->resolveJabatan($approver),
                'grade' => strtoupper(trim((string) ($approver->grade ?? ''))),
                'state' => 'pending',
                'acted_at' => null,
            ];

            if (count($lanes) >= 2) {
                break;
            }
        }

        if (count($lanes) === 1 && count($baks) === 1) {
            $submitter = $this->resolveSubmitterForBak($baks[0]);
            if ($submitter !== null) {
                $approverIds = $chainService->orderedApproverIds($submitter);
                $secondId = $approverIds[1] ?? null;
                if ($secondId !== null) {
                    $approver = MasterKaryawan::query()
                        ->where('id', $secondId)
                        ->where('is_active', true)
                        ->first(['id', 'nama_lengkap', 'grade', 'department']);
                    if ($approver !== null) {
                        $lanes[] = [
                            'slot' => 'diketahui_2',
                            'berita_acara_id' => (int) $baks[0]->id,
                            'karyawan_id' => (int) $approver->id,
                            'nama' => trim((string) $approver->nama_lengkap),
                            'jabatan' => $this->resolveJabatan($approver),
                            'grade' => strtoupper(trim((string) ($approver->grade ?? ''))),
                            'state' => 'pending',
                            'acted_at' => null,
                        ];
                    }
                }
            }
        }

        return $lanes;
    }

    /**
     * @param  list<array<string, mixed>>  $lanes
     */
    public function seedDiketahuiRows(int $investigasiId, array $lanes): void
    {
        $urutanMap = [0 => 3, 1 => 4];
        $now = Carbon::now();

        foreach ($lanes as $index => $lane) {
            $urutan = $urutanMap[$index] ?? null;
            if ($urutan === null) {
                continue;
            }

            DB::table('bak_investigasi_pengesahan')
                ->where('bak_investigasi_id', $investigasiId)
                ->where('urutan', $urutan)
                ->update([
                    'nama' => $lane['nama'] ?? null,
                    'jabatan' => $lane['jabatan'] ?? null,
                    'berita_acara_id' => $lane['berita_acara_id'] ?? null,
                    'karyawan_id' => $lane['karyawan_id'] ?? null,
                    'state' => 'pending',
                    'updated_at' => $now,
                ]);
        }
    }

    public function approveDiketahui(int $investigasiId, MasterKaryawan $actor): bool
    {
        $row = DB::table('bak_investigasi')->where('id', $investigasiId)->first();
        if ($row === null) {
            return false;
        }

        $lanes = $this->decodeChain($row->approval_chain ?? null);
        $changed = false;
        $now = Carbon::now();

        foreach ($lanes as $index => $lane) {
            $pendingId = (int) ($lane['karyawan_id'] ?? 0);
            if (($lane['state'] ?? '') !== 'pending' || $pendingId !== (int) $actor->id) {
                continue;
            }

            $lanes[$index]['state'] = 'approved';
            $lanes[$index]['acted_at'] = $now->toIso8601String();
            $lanes[$index]['actor_name'] = $actor->nama_lengkap;

            $urutan = $index === 0 ? 3 : 4;
            DB::table('bak_investigasi_pengesahan')
                ->where('bak_investigasi_id', $investigasiId)
                ->where('urutan', $urutan)
                ->update([
                    'nama' => $actor->nama_lengkap,
                    'jabatan' => $this->resolveJabatan($actor),
                    'tanggal' => $now->toDateString(),
                    'karyawan_id' => $actor->id,
                    'state' => 'approved',
                    'acted_at' => $now,
                    'actor_name' => $actor->nama_lengkap,
                    'updated_at' => $now,
                ]);

            $changed = true;
            break;
        }

        if (!$changed) {
            return false;
        }

        DB::table('bak_investigasi')
            ->where('id', $investigasiId)
            ->update([
                'approval_chain' => json_encode($lanes, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);

        if ($this->allDiketahuiApproved($lanes)) {
            $this->tryAutoIssue($investigasiId);
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $lanes
     */
    public function allDiketahuiApproved(array $lanes): bool
    {
        if ($lanes === []) {
            return true;
        }

        foreach ($lanes as $lane) {
            if (($lane['state'] ?? '') !== 'approved') {
                return false;
            }
        }

        return true;
    }

    public function pengesahanComplete(int $investigasiId): bool
    {
        if (!Schema::hasTable('bak_investigasi_pengesahan')) {
            return false;
        }

        $rows = DB::table('bak_investigasi_pengesahan')
            ->where('bak_investigasi_id', $investigasiId)
            ->get();

        foreach ($rows as $row) {
            if (($row->keterangan ?? '') === self::SLOT_DIKETAHUI && ($row->state ?? '') === 'pending') {
                if (trim((string) ($row->nama ?? '')) === '') {
                    continue;
                }

                return false;
            }
            if (in_array($row->keterangan, [self::SLOT_DISUSUN, self::SLOT_DISETUJUI], true)
                && ($row->state ?? '') !== 'approved') {
                return false;
            }
        }

        $header = DB::table('bak_investigasi')->where('id', $investigasiId)->first();
        $lanes = $this->decodeChain($header->approval_chain ?? null);
        if ($lanes !== [] && !$this->allDiketahuiApproved($lanes)) {
            return false;
        }

        return true;
    }

    public function tryAutoIssue(int $investigasiId): void
    {
        if (!$this->pengesahanComplete($investigasiId)) {
            return;
        }

        DB::table('bak_investigasi')
            ->where('id', $investigasiId)
            ->where('status', InvestigasiAwalStatus::MENUNGGU_PENGESAHAN)
            ->update([
                'status' => InvestigasiAwalStatus::ISSUED,
                'published_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
    }

    public function viewerCanApproveDiketahui(int $investigasiId, int $viewerKaryawanId): bool
    {
        $row = DB::table('bak_investigasi')->where('id', $investigasiId)->first();
        if ($row === null || ($row->status ?? '') !== InvestigasiAwalStatus::MENUNGGU_PENGESAHAN) {
            return false;
        }

        $lanes = $this->decodeChain($row->approval_chain ?? null);
        foreach ($lanes as $lane) {
            if (($lane['state'] ?? '') === 'pending' && (int) ($lane['karyawan_id'] ?? 0) === $viewerKaryawanId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function linkedBakIds(int $investigasiId): array
    {
        if (!Schema::hasTable('bak_investigasi_berita_acara')) {
            return [];
        }

        return DB::table('bak_investigasi_berita_acara')
            ->where('bak_investigasi_id', $investigasiId)
            ->pluck('berita_acara_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function resolveSubmitterForBak(BeritaAcaraKejadianDisipliner $bak): ?MasterKaryawan
    {
        $penandatanganId = (int) ($bak->penandatangan_karyawan_id ?? 0);
        if ($penandatanganId > 0) {
            $row = MasterKaryawan::query()
                ->where('id', $penandatanganId)
                ->where('is_active', true)
                ->first();
            if ($row !== null) {
                return $row;
            }
        }

        $name = trim((string) ($bak->created_by ?? ''));
        if ($name === '') {
            return null;
        }

        return MasterKaryawan::query()
            ->where('is_active', true)
            ->where('nama_lengkap', $name)
            ->first();
    }

    private function resolveJabatan(MasterKaryawan $karyawan): string
    {
        $grade = trim((string) ($karyawan->grade ?? ''));
        $dept = trim((string) ($karyawan->department ?? ''));

        if ($grade !== '' && $dept !== '') {
            return $grade . ' — ' . $dept;
        }

        return $grade !== '' ? $grade : $dept;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeChain($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}