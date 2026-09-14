<?php

namespace App\Services;

use App\Models\KebijakanDokumen;
use App\Models\RequestKebijakan;
use Illuminate\Http\Request;

class RequestKebijakanRevisionService
{
    public const SECTIONS = [
        'judul',
        'tujuan',
        'ruang_lingkup',
        'definisi',
        'isi_ketetapan',
    ];

    public const SECTION_LABELS = [
        'judul' => 'Judul',
        'tujuan' => 'Tujuan',
        'ruang_lingkup' => 'Ruang Lingkup',
        'definisi' => 'Definisi',
        'isi_ketetapan' => 'Isi Ketetapan',
    ];

    public static function listActiveDocuments(?string $search = null, int $limit = 30): array
    {
        $limit = max(5, min(50, $limit));
        $keyword = trim((string) $search);

        $query = KebijakanDokumen::query()
            ->from('kebijakan_dokumen')
            ->where('kebijakan_dokumen.is_active', true)
            ->where('kebijakan_dokumen.status', 'active')
            ->with(['drafting']);

        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->where('kebijakan_dokumen.no_dokumen', 'like', '%' . $keyword . '%')
                    ->orWhere('kebijakan_dokumen.judul', 'like', '%' . $keyword . '%')
                    ->orWhereHas('drafting', function ($draft) use ($keyword) {
                        $draft->where('divisi_bagian', 'like', '%' . $keyword . '%');
                    });
            });
        }

        $documents = $query
            ->orderByDesc('kebijakan_dokumen.director_approved_at')
            ->limit($limit)
            ->get();

        $pendingMap = self::getPendingRevisionMap(
            $documents->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        return $documents
            ->map(function (KebijakanDokumen $dokumen) use ($pendingMap) {
                return self::formatActiveDocumentRow($dokumen, !empty($pendingMap[(int) $dokumen->id]));
            })
            ->values()
            ->all();
    }

    public static function getRevisionBaseline(int $dokumenId, ?int $excludeRequestId = null): array
    {
        $dokumen = KebijakanDokumen::with('drafting')->findOrFail($dokumenId);

        if (!$dokumen->is_active || $dokumen->status !== 'active') {
            abort(422, 'Dokumen ketetapan tidak aktif atau tidak ditemukan.');
        }

        if (!$dokumen->drafting) {
            abort(422, 'Konten dokumen ketetapan belum tersedia.');
        }

        self::assertCanRequestRevision($dokumenId, $excludeRequestId);

        $drafting = $dokumen->drafting;

        return [
            'dokumen_id' => $dokumen->id,
            'no_dokumen' => $dokumen->no_dokumen,
            'judul' => $drafting->judul ?? $dokumen->judul,
            'divisi_bagian' => $drafting->divisi_bagian,
            'revisian' => (int) ($dokumen->revisian ?? 0),
            'cetakan' => (int) ($dokumen->cetakan ?? 1),
            'baseline_snapshot' => self::buildBaselineSnapshot($drafting),
        ];
    }

    public static function hasPendingRevision(int $dokumenId, ?int $excludeRequestId = null): bool
    {
        $query = RequestKebijakan::query()
            ->where('parent_kebijakan_dokumen_id', $dokumenId)
            ->where('kategori', 'revision')
            ->where('is_active', true)
            ->whereNotIn('status', ['rejected']);

        if ($excludeRequestId) {
            $query->where('id', '!=', $excludeRequestId);
        }

        return $query->where(function ($sub) {
            $sub->whereIn('status', [
                'waiting_approval',
                'approved',
                'on_process',
                'pending_user_review',
                'pending_user_reject_review',
                'pending_legal_final',
                'pending_director_approval',
            ])->orWhere(function ($completed) {
                $completed->where('status', 'completed')
                    ->whereDoesntHave('activeKebijakanDokumen');
            });
        })->exists();
    }

    public static function assertCanRequestRevision(int $dokumenId, ?int $excludeRequestId = null): void
    {
        if (self::hasPendingRevision($dokumenId, $excludeRequestId)) {
            abort(422, 'Dokumen ketetapan ini masih memiliki pengajuan revisi yang belum selesai.');
        }
    }

    public static function validateRevisionPayload(Request $request, ?int $excludeRequestId = null): array
    {
        $parentId = (int) $request->input('parent_kebijakan_dokumen_id', 0);

        if ($parentId <= 0) {
            abort(422, 'Dokumen ketetapan induk wajib dipilih.');
        }

        $baselineData = self::getRevisionBaseline($parentId, $excludeRequestId);
        $baseline = $baselineData['baseline_snapshot'];

        $selectedSections = self::normalizeSelectedSections($request->input('selected_sections', []));
        $proposedChanges = self::normalizeProposedChanges($request->input('proposed_changes', []));

        if (empty($selectedSections)) {
            abort(422, 'Minimal satu bagian revisi wajib dipilih.');
        }

        foreach ($selectedSections as $section) {
            $proposed = $proposedChanges[$section] ?? null;

            if ($section === 'judul') {
                if (!is_string($proposed) || trim($proposed) === '') {
                    abort(422, 'Usulan revisi judul wajib diisi.');
                }
                continue;
            }

            if (self::isEmptyHtml(is_string($proposed) ? $proposed : null)) {
                $label = self::SECTION_LABELS[$section] ?? $section;
                abort(422, "Usulan revisi {$label} wajib diisi.");
            }
        }

        $merged = self::mergeContent($baseline, $proposedChanges, $selectedSections);
        $catatan = (string) $request->input('catatan', '');

        $revisionMeta = [
            'parent_kebijakan_dokumen_id' => $parentId,
            'parent_no_dokumen' => $baselineData['no_dokumen'],
            'parent_judul' => $baseline['judul'] ?? $baselineData['judul'],
            'parent_divisi_bagian' => $baselineData['divisi_bagian'],
            'selected_sections' => $selectedSections,
            'baseline_snapshot' => $baseline,
            'proposed_changes' => array_intersect_key($proposedChanges, array_flip($selectedSections)),
        ];

        return [
            'kategori' => 'revision',
            'parent_kebijakan_dokumen_id' => $parentId,
            'revision_meta' => $revisionMeta,
            'judul' => $merged['judul'],
            'tujuan' => $merged['tujuan'],
            'ruang_lingkup' => $merged['ruang_lingkup'],
            'definisi' => $merged['definisi'],
            'isi_ketetapan' => $merged['isi_ketetapan'],
            'catatan' => self::isEmptyHtml($catatan) ? null : $catatan,
        ];
    }

    public static function decodeRevisionMeta($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    public static function resolveMergedContent(RequestKebijakan $record): array
    {
        $meta = self::decodeRevisionMeta($record->revision_meta);

        if (!$meta) {
            return self::resolveRequestContent($record);
        }

        $baseline = $meta['baseline_snapshot'] ?? [];
        $proposed = $meta['proposed_changes'] ?? [];
        $selected = $meta['selected_sections'] ?? [];

        return self::mergeContent($baseline, $proposed, $selected);
    }

    /**
     * Konten awal draft legal: untuk revisi pakai isi dokumen induk (baseline),
     * bukan usulan pemohon — legal menyesuaikan manual.
     */
    public static function resolveDraftInitialContent(RequestKebijakan $record): array
    {
        if (strtolower((string) ($record->kategori ?? '')) === 'revision') {
            $meta = self::decodeRevisionMeta($record->revision_meta);
            $baseline = $meta['baseline_snapshot'] ?? [];

            if (!empty($baseline)) {
                return [
                    'judul' => $baseline['judul'] ?? $record->judul,
                    'tujuan' => $baseline['tujuan'] ?? '',
                    'ruang_lingkup' => $baseline['ruang_lingkup'] ?? '',
                    'definisi' => $baseline['definisi'] ?? '',
                    'isi_ketetapan' => $baseline['isi_ketetapan'] ?? '',
                ];
            }
        }

        return self::resolveRequestContent($record);
    }

    public static function isRevisionRequest(RequestKebijakan $record): bool
    {
        return strtolower((string) ($record->kategori ?? '')) === 'revision';
    }

    private static function resolveRequestContent(RequestKebijakan $record): array
    {
        return [
            'judul' => $record->judul,
            'tujuan' => $record->tujuan,
            'ruang_lingkup' => $record->ruang_lingkup,
            'definisi' => $record->definisi,
            'isi_ketetapan' => $record->isi_ketetapan,
        ];
    }

    public static function resolveParentDivisiBagian(RequestKebijakan $record): ?string
    {
        $meta = self::decodeRevisionMeta($record->revision_meta);

        if (!empty($meta['parent_divisi_bagian'])) {
            return $meta['parent_divisi_bagian'];
        }

        $parentId = $record->parent_kebijakan_dokumen_id;

        if (!$parentId) {
            return null;
        }

        $parent = KebijakanDokumen::with('drafting')->find($parentId);

        return $parent ? optional($parent->drafting)->divisi_bagian : null;
    }

    public static function resolveLegalFinalDefaults(RequestKebijakan $record): array
    {
        if (strtolower((string) ($record->kategori ?? '')) !== 'revision') {
            return [
                'terbitan' => 1,
                'revisian' => 0,
            ];
        }

        $parentId = $record->parent_kebijakan_dokumen_id;

        if (!$parentId) {
            return [
                'terbitan' => 1,
                'revisian' => 0,
            ];
        }

        $parent = KebijakanDokumen::find($parentId);

        if (!$parent) {
            return [
                'terbitan' => 1,
                'revisian' => 0,
            ];
        }

        return [
            'terbitan' => max(1, (int) ($parent->cetakan ?? 1) + 1),
            'revisian' => max(0, (int) ($parent->revisian ?? 0) + 1),
        ];
    }

    public static function resolveParentNoDokumen(RequestKebijakan $record): ?string
    {
        $meta = self::decodeRevisionMeta($record->revision_meta);

        if (!empty($meta['parent_no_dokumen'])) {
            return $meta['parent_no_dokumen'];
        }

        $parentId = $record->parent_kebijakan_dokumen_id;

        if (!$parentId) {
            return null;
        }

        return optional(KebijakanDokumen::find($parentId))->no_dokumen;
    }

    private static function formatActiveDocumentRow(KebijakanDokumen $dokumen, bool $hasPendingRevision): array
    {
        return [
            'id' => $dokumen->id,
            'no_dokumen' => $dokumen->no_dokumen,
            'judul' => $dokumen->judul,
            'divisi_bagian' => optional($dokumen->drafting)->divisi_bagian,
            'revisian' => (int) ($dokumen->revisian ?? 0),
            'cetakan' => (int) ($dokumen->cetakan ?? 1),
            'has_pending_revision' => $hasPendingRevision,
        ];
    }

    private static function getPendingRevisionMap(array $dokumenIds): array
    {
        if (empty($dokumenIds)) {
            return [];
        }

        $pendingIds = RequestKebijakan::query()
            ->whereIn('parent_kebijakan_dokumen_id', $dokumenIds)
            ->where('kategori', 'revision')
            ->where('is_active', true)
            ->whereNotIn('status', ['rejected'])
            ->where(function ($sub) {
                $sub->whereIn('status', [
                    'waiting_approval',
                    'approved',
                    'on_process',
                    'pending_user_review',
                    'pending_user_reject_review',
                    'pending_legal_final',
                    'pending_director_approval',
                ])->orWhere(function ($completed) {
                    $completed->where('status', 'completed')
                        ->whereDoesntHave('activeKebijakanDokumen');
                });
            })
            ->pluck('parent_kebijakan_dokumen_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $map = [];

        foreach ($pendingIds as $id) {
            $map[$id] = true;
        }

        return $map;
    }

    private static function buildBaselineSnapshot($drafting): array
    {
        return [
            'judul' => $drafting->judul ?? '',
            'tujuan' => $drafting->tujuan ?? '',
            'ruang_lingkup' => $drafting->ruang_lingkup ?? '',
            'definisi' => $drafting->definisi ?? '',
            'isi_ketetapan' => $drafting->isi_ketetapan ?? '',
        ];
    }

    private static function normalizeSelectedSections($sections): array
    {
        if (!is_array($sections)) {
            return [];
        }

        $normalized = [];

        foreach ($sections as $section) {
            $key = strtolower(trim((string) $section));

            if (in_array($key, self::SECTIONS, true) && !in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        return $normalized;
    }

    private static function normalizeProposedChanges($changes): array
    {
        if (!is_array($changes)) {
            return [];
        }

        $normalized = [];

        foreach (self::SECTIONS as $section) {
            if (!array_key_exists($section, $changes)) {
                continue;
            }

            $value = $changes[$section];

            if ($section === 'judul') {
                $normalized[$section] = trim((string) $value);
                continue;
            }

            $normalized[$section] = (string) $value;
        }

        return $normalized;
    }

    private static function mergeContent(array $baseline, array $proposed, array $selectedSections): array
    {
        $merged = [];

        foreach (self::SECTIONS as $section) {
            if (in_array($section, $selectedSections, true) && array_key_exists($section, $proposed)) {
                $merged[$section] = $proposed[$section];
                continue;
            }

            $merged[$section] = $baseline[$section] ?? '';
        }

        return $merged;
    }

    private static function isEmptyHtml(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return true;
        }

        $text = trim(strip_tags(html_entity_decode($value)));

        return $text === '';
    }
}
