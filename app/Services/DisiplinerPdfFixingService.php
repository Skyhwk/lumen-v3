<?php

namespace App\Services;

use App\Models\BeritaAcaraKejadianDisipliner;
use App\Models\SuratPeringatanDisipliner;
use App\Models\SuratTeguranDisipliner;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DisiplinerPdfFixingService
{
    /** @var array<string, list<string>> */
    private const STATUS_TRANSITIONS = [
        SuratPeringatanDisipliner::STATUS_DRAFT => [
            SuratPeringatanDisipliner::STATUS_ISSUED,
            SuratPeringatanDisipliner::STATUS_CANCELLED,
        ],
        SuratPeringatanDisipliner::STATUS_ISSUED => [
            SuratPeringatanDisipliner::STATUS_DRAFT,
            SuratPeringatanDisipliner::STATUS_CANCELLED,
        ],
        SuratPeringatanDisipliner::STATUS_CANCELLED => [
            SuratPeringatanDisipliner::STATUS_DRAFT,
        ],
    ];
    public const JENIS_SP = 'sp';

    public const JENIS_BAK = 'bak';

    public const JENIS_ST = 'st';

    public function search(string $query, ?string $jenisFilter = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $items = [];

        if ($jenisFilter === null || $jenisFilter === self::JENIS_SP) {
            $items = array_merge($items, $this->searchSuratPeringatan($query));
        }

        if ($jenisFilter === null || $jenisFilter === self::JENIS_BAK) {
            $items = array_merge($items, $this->searchBeritaAcaraKejadian($query));
        }

        if ($jenisFilter === null || $jenisFilter === self::JENIS_ST) {
            $items = array_merge($items, $this->searchSuratTeguran($query));
        }

        usort($items, static function ($a, $b) {
            $dateA = (string) ($a['tanggal_terbit'] ?? $a['tanggal_kejadian'] ?? '');
            $dateB = (string) ($b['tanggal_terbit'] ?? $b['tanggal_kejadian'] ?? '');

            return strcmp($dateB, $dateA);
        });

        return array_slice($items, 0, 30);
    }

    public function regenerate(string $jenis, int $id): array
    {
        $jenis = strtolower(trim($jenis));

        if ($jenis === self::JENIS_SP) {
            return $this->regenerateSuratPeringatan($id);
        }

        if ($jenis === self::JENIS_BAK) {
            return $this->regenerateBeritaAcaraKejadian($id);
        }

        if ($jenis === self::JENIS_ST) {
            return $this->regenerateSuratTeguran($id);
        }

        throw new RuntimeException('Jenis dokumen disipliner tidak valid.');
    }

    /**
     * @return list<string>
     */
    public function allowedStatusTargets(?string $currentStatus): array
    {
        $current = (string) $currentStatus;

        return self::STATUS_TRANSITIONS[$current] ?? [];
    }

    public function updateStatus(string $jenis, int $id, string $newStatus, string $updatedBy): array
    {
        $jenis = strtolower(trim($jenis));
        $newStatus = strtolower(trim($newStatus));

        if (!in_array($newStatus, [
            SuratPeringatanDisipliner::STATUS_DRAFT,
            SuratPeringatanDisipliner::STATUS_ISSUED,
            SuratPeringatanDisipliner::STATUS_CANCELLED,
        ], true)) {
            throw new RuntimeException('Status tujuan tidak valid.');
        }

        return DB::transaction(function () use ($jenis, $id, $newStatus, $updatedBy) {
            if ($jenis === self::JENIS_SP) {
                return $this->applyStatusChangeSuratPeringatan($id, $newStatus, $updatedBy);
            }

            if ($jenis === self::JENIS_BAK) {
                return $this->applyStatusChangeBeritaAcaraKejadian($id, $newStatus, $updatedBy);
            }

            if ($jenis === self::JENIS_ST) {
                return $this->applyStatusChangeSuratTeguran($id, $newStatus, $updatedBy);
            }

            throw new RuntimeException('Jenis dokumen disipliner tidak valid.');
        });
    }

    private function applyStatusChangeSuratPeringatan(int $id, string $newStatus, string $updatedBy): array
    {
        $letter = SuratPeringatanDisipliner::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if ($letter === null) {
            throw new RuntimeException('Surat peringatan tidak ditemukan.');
        }

        $this->assertStatusTransition($letter->status, $newStatus);

        $letter->status = $newStatus;
        $letter->updated_by = $updatedBy;
        $letter->updated_at = Carbon::now();

        if ($newStatus === SuratPeringatanDisipliner::STATUS_DRAFT) {
            $letter->published_by = null;
        }

        $letter->save();

        return $this->formatStatusResult(self::JENIS_SP, $letter);
    }

    private function applyStatusChangeBeritaAcaraKejadian(int $id, string $newStatus, string $updatedBy): array
    {
        $document = BeritaAcaraKejadianDisipliner::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if ($document === null) {
            throw new RuntimeException('Berita acara kejadian tidak ditemukan.');
        }

        $this->assertStatusTransition($document->status, $newStatus);

        $document->status = $newStatus;
        $document->updated_by = $updatedBy;
        $document->updated_at = Carbon::now();

        if ($newStatus === BeritaAcaraKejadianDisipliner::STATUS_DRAFT) {
            $document->published_by = null;
        }

        $document->save();

        return $this->formatStatusResult(self::JENIS_BAK, $document);
    }

    private function applyStatusChangeSuratTeguran(int $id, string $newStatus, string $updatedBy): array
    {
        $letter = SuratTeguranDisipliner::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if ($letter === null) {
            throw new RuntimeException('Surat teguran tidak ditemukan.');
        }

        $this->assertStatusTransition($letter->status, $newStatus);

        $letter->status = $newStatus;
        $letter->updated_by = $updatedBy;
        $letter->updated_at = Carbon::now('Asia/Jakarta');

        if ($newStatus === SuratTeguranDisipliner::STATUS_DRAFT) {
            $letter->published_by = null;
        }

        $letter->save();

        return $this->formatStatusResult(self::JENIS_ST, $letter);
    }

    private function assertStatusTransition(?string $currentStatus, string $newStatus): void
    {
        $current = (string) $currentStatus;

        if ($current === $newStatus) {
            throw new RuntimeException('Status dokumen sudah ' . $this->mapStatusLabel($newStatus) . '.');
        }

        $allowed = $this->allowedStatusTargets($current);
        if (!in_array($newStatus, $allowed, true)) {
            throw new RuntimeException(
                'Perubahan status dari '
                . $this->mapStatusLabel($current)
                . ' ke '
                . $this->mapStatusLabel($newStatus)
                . ' tidak diizinkan.'
            );
        }
    }

    private function formatStatusResult(string $jenis, $model): array
    {
        $jenisLabels = [
            self::JENIS_SP => 'Surat Peringatan',
            self::JENIS_BAK => 'Berita Acara Kejadian',
            self::JENIS_ST => 'Surat Teguran',
        ];

        return [
            'jenis' => $jenis,
            'jenis_label' => $jenisLabels[$jenis] ?? $jenis,
            'id' => (int) $model->id,
            'nomor_surat' => (string) $model->nomor_surat,
            'status' => (string) $model->status,
            'status_label' => $this->mapStatusLabel($model->status),
        ];
    }

    private function regenerateSuratPeringatan(int $id): array
    {
        $detail = $this->findSuratPeringatanRow($id);
        if ($detail === null) {
            throw new RuntimeException('Surat peringatan tidak ditemukan.');
        }

        if (!in_array($detail->status, [
            SuratPeringatanDisipliner::STATUS_DRAFT,
            SuratPeringatanDisipliner::STATUS_ISSUED,
        ], true)) {
            throw new RuntimeException('Status dokumen tidak mendukung render ulang PDF.');
        }

        $letter = SuratPeringatanDisipliner::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if ($letter === null) {
            throw new RuntimeException('Surat peringatan tidak ditemukan.');
        }

        $stored = app(SuratPeringatanDocumentService::class)->regenerateStoredPdf($letter, $detail);

        return $this->formatResult(self::JENIS_SP, $stored, $detail);
    }

    private function regenerateBeritaAcaraKejadian(int $id): array
    {
        $detail = $this->findBeritaAcaraRow($id);
        if ($detail === null) {
            throw new RuntimeException('Berita acara kejadian tidak ditemukan.');
        }

        if (!in_array($detail->status, [
            BeritaAcaraKejadianDisipliner::STATUS_DRAFT,
            BeritaAcaraKejadianDisipliner::STATUS_ISSUED,
        ], true)) {
            throw new RuntimeException('Status dokumen tidak mendukung render ulang PDF.');
        }

        $document = BeritaAcaraKejadianDisipliner::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if ($document === null) {
            throw new RuntimeException('Berita acara kejadian tidak ditemukan.');
        }

        $stored = app(BeritaAcaraKejadianDocumentService::class)->regenerateStoredPdf($document, $detail);

        return $this->formatResult(self::JENIS_BAK, $stored, $detail);
    }

    private function regenerateSuratTeguran(int $id): array
    {
        $detail = $this->findSuratTeguranRow($id);
        if ($detail === null) {
            throw new RuntimeException('Surat teguran tidak ditemukan.');
        }

        if (!in_array($detail->status, [
            SuratTeguranDisipliner::STATUS_DRAFT,
            SuratTeguranDisipliner::STATUS_ISSUED,
        ], true)) {
            throw new RuntimeException('Status dokumen tidak mendukung render ulang PDF.');
        }

        $letter = SuratTeguranDisipliner::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if ($letter === null) {
            throw new RuntimeException('Surat teguran tidak ditemukan.');
        }

        $stored = app(SuratTeguranDocumentService::class)->regenerateStoredPdf($letter, $detail);

        return $this->formatResult(self::JENIS_ST, $stored, $detail);
    }

    private function formatResult(string $jenis, $model, object $detail): array
    {
        $jenisLabels = [
            self::JENIS_SP => 'Surat Peringatan',
            self::JENIS_BAK => 'Berita Acara Kejadian',
            self::JENIS_ST => 'Surat Teguran',
        ];

        return [
            'jenis' => $jenis,
            'jenis_label' => $jenisLabels[$jenis] ?? $jenis,
            'id' => (int) $model->id,
            'nomor_surat' => (string) $model->nomor_surat,
            'status' => (string) $model->status,
            'nama_karyawan' => trim((string) ($detail->nama_lengkap ?? '')) ?: '-',
            'file_path' => (string) ($model->file_path ?? ''),
            'nama_berkas' => (string) ($model->nama_berkas ?? ''),
            'file_size' => (int) ($model->file_size ?? 0),
        ];
    }

    private function searchSuratPeringatan(string $query): array
    {
        $base = DB::table('surat_peringatan_disipliner as spd')
            ->leftJoin('master_karyawan as mk', 'spd.karyawan_id', '=', 'mk.id')
            ->where('spd.is_active', true)
            ->whereIn('spd.status', [
                SuratPeringatanDisipliner::STATUS_DRAFT,
                SuratPeringatanDisipliner::STATUS_ISSUED,
                SuratPeringatanDisipliner::STATUS_CANCELLED,
            ])
            ->select(
                'spd.id',
                'spd.nomor_surat',
                'spd.status',
                'spd.tanggal_terbit',
                'spd.file_path',
                'mk.nama_lengkap'
            );

        $this->applySearchFilter($base, $query, 'spd');

        return $base
            ->orderByDesc('spd.tanggal_terbit')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'jenis' => self::JENIS_SP,
                'jenis_label' => 'Surat Peringatan',
                'id' => (int) $row->id,
                'nomor_surat' => (string) $row->nomor_surat,
                'status' => (string) $row->status,
                'status_label' => $this->mapStatusLabel($row->status),
                'tanggal_terbit' => (string) $row->tanggal_terbit,
                'nama_karyawan' => trim((string) ($row->nama_lengkap ?? '')) ?: '-',
                'has_pdf' => trim((string) ($row->file_path ?? '')) !== '',
            ])
            ->all();
    }

    private function searchBeritaAcaraKejadian(string $query): array
    {
        $base = DB::table('berita_acara_kejadian_disipliner as bak')
            ->leftJoin('master_karyawan as mk', 'bak.karyawan_id', '=', 'mk.id')
            ->where('bak.is_active', true)
            ->whereIn('bak.status', [
                BeritaAcaraKejadianDisipliner::STATUS_DRAFT,
                BeritaAcaraKejadianDisipliner::STATUS_ISSUED,
                BeritaAcaraKejadianDisipliner::STATUS_CANCELLED,
            ])
            ->select(
                'bak.id',
                'bak.nomor_surat',
                'bak.status',
                'bak.tanggal_kejadian',
                'bak.file_path',
                'mk.nama_lengkap'
            );

        $this->applySearchFilter($base, $query, 'bak');

        return $base
            ->orderByDesc('bak.tanggal_kejadian')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'jenis' => self::JENIS_BAK,
                'jenis_label' => 'Berita Acara Kejadian',
                'id' => (int) $row->id,
                'nomor_surat' => (string) $row->nomor_surat,
                'status' => (string) $row->status,
                'status_label' => $this->mapStatusLabel($row->status),
                'tanggal_kejadian' => (string) $row->tanggal_kejadian,
                'nama_karyawan' => trim((string) ($row->nama_lengkap ?? '')) ?: '-',
                'has_pdf' => trim((string) ($row->file_path ?? '')) !== '',
            ])
            ->all();
    }

    private function searchSuratTeguran(string $query): array
    {
        $base = DB::table('surat_teguran_disipliner as std')
            ->leftJoin('master_karyawan as mk', 'std.karyawan_id', '=', 'mk.id')
            ->where('std.is_active', true)
            ->whereIn('std.status', [
                SuratTeguranDisipliner::STATUS_DRAFT,
                SuratTeguranDisipliner::STATUS_ISSUED,
                SuratTeguranDisipliner::STATUS_CANCELLED,
            ])
            ->select(
                'std.id',
                'std.nomor_surat',
                'std.status',
                'std.tanggal_terbit',
                'std.file_path',
                'mk.nama_lengkap'
            );

        $this->applySearchFilter($base, $query, 'std');

        return $base
            ->orderByDesc('std.tanggal_terbit')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'jenis' => self::JENIS_ST,
                'jenis_label' => 'Surat Teguran',
                'id' => (int) $row->id,
                'nomor_surat' => (string) $row->nomor_surat,
                'status' => (string) $row->status,
                'status_label' => $this->mapStatusLabel($row->status),
                'tanggal_terbit' => (string) $row->tanggal_terbit,
                'nama_karyawan' => trim((string) ($row->nama_lengkap ?? '')) ?: '-',
                'has_pdf' => trim((string) ($row->file_path ?? '')) !== '',
            ])
            ->all();
    }

    private function applySearchFilter($query, string $keyword, string $alias): void
    {
        if (ctype_digit($keyword)) {
            $query->where(function ($sub) use ($keyword, $alias) {
                $sub->where($alias . '.id', (int) $keyword)
                    ->orWhere($alias . '.nomor_surat', 'like', '%' . $keyword . '%');
            });

            return;
        }

        $like = '%' . $keyword . '%';
        $query->where(function ($sub) use ($like, $alias) {
            $sub->where($alias . '.nomor_surat', 'like', $like)
                ->orWhere('mk.nama_lengkap', 'like', $like)
                ->orWhere('mk.nik_karyawan', 'like', $like);
        });
    }

    private function findSuratPeringatanRow(int $id): ?object
    {
        return DB::table('surat_peringatan_disipliner as spd')
            ->leftJoin('master_karyawan as mk', 'spd.karyawan_id', '=', 'mk.id')
            ->leftJoin('master_divisi as md', 'mk.id_department', '=', 'md.id')
            ->where('spd.id', $id)
            ->where('spd.is_active', true)
            ->select(
                'spd.*',
                'mk.nik_karyawan',
                'mk.nama_lengkap',
                'mk.department',
                'mk.jabatan',
                'md.nama_divisi'
            )
            ->first();
    }

    private function findBeritaAcaraRow(int $id): ?object
    {
        return DB::table('berita_acara_kejadian_disipliner as bak')
            ->leftJoin('master_karyawan as mk', 'bak.karyawan_id', '=', 'mk.id')
            ->leftJoin('master_divisi as md', 'mk.id_department', '=', 'md.id')
            ->where('bak.id', $id)
            ->where('bak.is_active', true)
            ->select(
                'bak.*',
                'mk.nik_karyawan',
                'mk.nama_lengkap',
                'mk.department',
                'mk.jabatan',
                'md.nama_divisi'
            )
            ->first();
    }

    private function findSuratTeguranRow(int $id): ?object
    {
        return DB::table('surat_teguran_disipliner as std')
            ->leftJoin('master_karyawan as mk', 'std.karyawan_id', '=', 'mk.id')
            ->leftJoin('master_divisi as md', 'mk.id_department', '=', 'md.id')
            ->where('std.id', $id)
            ->where('std.is_active', true)
            ->select(
                'std.*',
                'mk.nik_karyawan',
                'mk.nama_lengkap',
                'mk.department',
                'mk.jabatan',
                'md.nama_divisi'
            )
            ->first();
    }

    private function mapStatusLabel(?string $status): string
    {
        $map = [
            SuratPeringatanDisipliner::STATUS_DRAFT => 'Menunggu pengesahan',
            SuratPeringatanDisipliner::STATUS_ISSUED => 'Terbit',
            BeritaAcaraKejadianDisipliner::STATUS_DRAFT => 'Menunggu pengesahan',
            BeritaAcaraKejadianDisipliner::STATUS_ISSUED => 'Terbit',
            SuratTeguranDisipliner::STATUS_DRAFT => 'Menunggu pengesahan',
            SuratTeguranDisipliner::STATUS_ISSUED => 'Terbit',
            SuratPeringatanDisipliner::STATUS_CANCELLED => 'Dibatalkan',
            BeritaAcaraKejadianDisipliner::STATUS_CANCELLED => 'Dibatalkan',
            SuratTeguranDisipliner::STATUS_CANCELLED => 'Dibatalkan',
        ];

        return $map[$status] ?? (string) $status;
    }
}
