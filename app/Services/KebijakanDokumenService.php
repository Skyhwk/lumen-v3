<?php

namespace App\Services;

use App\Models\KebijakanDokumen;
use App\Models\RequestKebijakan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KebijakanDokumenService
{
    private const ROMAN_MONTHS = [
        '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV',
        '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII',
        '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII',
    ];

    public static function generateNoDokumen(): string
    {
        $year = date('y');
        $month = self::ROMAN_MONTHS[date('m')];
        $prefix = "KP/{$year}-{$month}/";

        $latest = KebijakanDokumen::where('no_dokumen', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(no_dokumen, "/", -1) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = 1;
        if ($latest) {
            $lastPart = substr($latest->no_dokumen, strrpos($latest->no_dokumen, '/') + 1);
            $nextNumber = (int) $lastPart + 1;
        }

        $padLength = max(4, strlen((string) $nextNumber));

        return $prefix . str_pad($nextNumber, $padLength, '0', STR_PAD_LEFT);
    }

    public static function submitLegalFinalVerification(RequestKebijakan $record, $employee, array $metadata): KebijakanDokumen
    {
        if ($record->status !== 'pending_legal_final' || !$record->is_active || !$record->drafting) {
            throw new \RuntimeException('Request tidak dapat diverifikasi final pada tahap ini');
        }

        if (!RequestKebijakanNotificationService::canAccessApprovalMenu($employee)) {
            throw new \RuntimeException('Anda tidak memiliki akses verifikasi final legal');
        }

        $now = Carbon::now();
        $by = $employee->nama_lengkap ?? 'Legal Manager';
        $terbitan = max(1, (int) ($metadata['terbitan'] ?? $metadata['cetakan'] ?? 1));
        $revisian = max(0, (int) ($metadata['revisian'] ?? $metadata['revisi'] ?? 0));
        $tanggalTerbitan = self::parseDate(
            $metadata['tanggal_terbitan'] ?? $metadata['tanggal_pengesahan'] ?? $now->format('Y-m-d')
        );

        $existing = KebijakanDokumen::query()
            ->where('request_kebijakan_id', $record->id)
            ->where('is_active', true)
            ->whereIn('status', ['pending_director', 'returned_to_legal'])
            ->orderByDesc('id')
            ->first();

        $parentDokumenId = self::resolveParentDokumenId($record);

        if ($existing) {
            $existing->update([
                'revisian' => $revisian,
                'cetakan' => $terbitan,
                'tanggal_pengesahan' => $tanggalTerbitan,
                'status' => 'pending_director',
                'legal_verified_by' => $by,
                'legal_verified_at' => $now,
                'director_rejected_by' => null,
                'director_rejected_at' => null,
                'director_rejected_note' => null,
                'updated_at' => $now,
            ]);

            $dokumen = $existing->fresh();
        } else {
            $dokumen = KebijakanDokumen::create([
                'request_kebijakan_id' => $record->id,
                'drafting_kebijakan_id' => $record->drafting->id,
                'parent_dokumen_id' => $parentDokumenId,
                'no_dokumen' => self::generateNoDokumen(),
                'judul' => $record->judul ?? $record->drafting->judul,
                'kategori' => strtolower((string) ($record->kategori ?? 'new')),
                'revisian' => $revisian,
                'cetakan' => $terbitan,
                'tanggal_pengesahan' => $tanggalTerbitan,
                'status' => 'pending_director',
                'legal_verified_by' => $by,
                'legal_verified_at' => $now,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $record->update([
            'status' => 'pending_director_approval',
            'reviewed_by' => $by,
            'reviewed_at' => $now,
        ]);

        $dokumen = $dokumen->fresh();
        KebijakanDokumenQrService::syncAndStore($dokumen, $by);

        RequestKebijakanNotificationService::legalFinalVerifiedPendingDirector($record->fresh(), $dokumen->fresh());

        return $dokumen->fresh();
    }

    public static function approveByDirector(KebijakanDokumen $dokumen, $director): KebijakanDokumen
    {
        if ($dokumen->status !== 'pending_director' || !$dokumen->is_active) {
            throw new \RuntimeException('Dokumen tidak dapat disahkan pada tahap ini');
        }

        if (!self::canDirectorAct($director)) {
            throw new \RuntimeException('Hanya Director yang dapat mengesahkan dokumen kebijakan');
        }

        $now = Carbon::now();
        $by = $director->nama_lengkap ?? 'Director';
        $record = RequestKebijakan::with('drafting')->findOrFail($dokumen->request_kebijakan_id);

        $dokumen->update([
            'status' => 'active',
            'director_approved_by' => $by,
            'director_approved_at' => $now,
            'director_rejected_by' => null,
            'director_rejected_at' => null,
            'director_rejected_note' => null,
            'updated_at' => $now,
        ]);

        if ($dokumen->parent_dokumen_id) {
            self::archiveSupersededParent($dokumen->parent_dokumen_id, $dokumen->id, $by);
        }

        if ($dokumen->kategori === 'termination' && $dokumen->parent_dokumen_id) {
            self::archiveTerminatedDocument($dokumen->parent_dokumen_id, $by);
        }

        $record->update([
            'status' => 'completed',
        ]);

        $dokumen = $dokumen->fresh();
        KebijakanDokumenQrService::syncAndStore($dokumen, $by);

        RequestKebijakanNotificationService::directorApprovedDocument($record->fresh(), $dokumen->fresh());

        return $dokumen->fresh();
    }

    public static function rejectByDirector(KebijakanDokumen $dokumen, $director, string $reason): KebijakanDokumen
    {
        if ($dokumen->status !== 'pending_director' || !$dokumen->is_active) {
            throw new \RuntimeException('Dokumen tidak dapat ditolak pada tahap ini');
        }

        if (!self::canDirectorAct($director)) {
            throw new \RuntimeException('Hanya Director yang dapat menolak pengesahan dokumen');
        }

        $now = Carbon::now();
        $by = $director->nama_lengkap ?? 'Director';
        $record = RequestKebijakan::findOrFail($dokumen->request_kebijakan_id);

        $dokumen->update([
            'status' => 'returned_to_legal',
            'director_rejected_by' => $by,
            'director_rejected_at' => $now,
            'director_rejected_note' => $reason,
            'updated_at' => $now,
        ]);

        $record->update([
            'status' => 'pending_legal_final',
        ]);

        RequestKebijakanNotificationService::directorRejectedToLegalFinal($record->fresh(), $dokumen->fresh(), $director);

        return $dokumen->fresh();
    }

    public static function canDirectorAct($employee): bool
    {
        return RequestKebijakanWorkflowService::normalizeGrade($employee->grade ?? '') === 'DIRECTOR';
    }

    public static function getTabCounts(): array
    {
        return [
            'pending_director' => KebijakanDokumen::query()
                ->where('is_active', true)
                ->where('status', 'pending_director')
                ->count(),
            'active' => KebijakanDokumen::query()
                ->where('is_active', true)
                ->where('status', 'active')
                ->count(),
            'archive' => KebijakanDokumen::query()
                ->where('is_active', true)
                ->where('status', 'archived')
                ->count(),
        ];
    }

    public static function buildScopeQuery(string $scope)
    {
        $query = KebijakanDokumen::query()
            ->from('kebijakan_dokumen')
            ->where('kebijakan_dokumen.is_active', true)
            ->with(['requestKebijakan.requester', 'drafting']);

        if ($scope === 'pending_director') {
            return $query->where('kebijakan_dokumen.status', 'pending_director')
                ->orderByDesc('kebijakan_dokumen.legal_verified_at');
        }

        if ($scope === 'archive') {
            return $query->where('kebijakan_dokumen.status', 'archived')
                ->orderByDesc('kebijakan_dokumen.archived_at');
        }

        return $query->where('kebijakan_dokumen.status', 'active')
            ->orderByDesc('kebijakan_dokumen.director_approved_at');
    }

    public static function resolveArchiveReasonLabel(?string $reason): string
    {
        $map = self::getArchiveReasonFilterMap();

        return $map[$reason] ?? ($reason ? ucfirst(str_replace('_', ' ', $reason)) : '-');
    }

    public static function getArchiveReasonFilterMap(): array
    {
        return [
            'terminated' => 'Terminasi',
            'deactivated' => 'Nonaktif',
            'superseded_revision' => 'Digantikan Revisi',
        ];
    }

    public static function resolveKategoriLabel(?string $kategori): string
    {
        return RequestKebijakanWorkflowService::resolveKategoriLabel($kategori);
    }

    private static function resolveParentDokumenId(RequestKebijakan $record): ?int
    {
        $kategori = strtolower((string) ($record->kategori ?? 'new'));

        if (!in_array($kategori, ['revision', 'termination'], true)) {
            return null;
        }

        $parentId = $record->parent_kebijakan_dokumen_id ?? null;

        if ($parentId) {
            return (int) $parentId;
        }

        return null;
    }

    private static function archiveSupersededParent(int $parentDokumenId, int $newDokumenId, string $by): void
    {
        $parent = KebijakanDokumen::find($parentDokumenId);

        if (!$parent || $parent->status !== 'active') {
            return;
        }

        $now = Carbon::now();

        $parent->update([
            'status' => 'archived',
            'archive_reason' => 'superseded_revision',
            'superseded_by_id' => $newDokumenId,
            'archived_at' => $now,
            'archived_by' => $by,
            'updated_at' => $now,
        ]);
    }

    private static function archiveTerminatedDocument(int $parentDokumenId, string $by): void
    {
        $parent = KebijakanDokumen::find($parentDokumenId);

        if (!$parent || $parent->status === 'archived') {
            return;
        }

        $now = Carbon::now();

        $parent->update([
            'status' => 'archived',
            'archive_reason' => 'terminated',
            'archived_at' => $now,
            'archived_by' => $by,
            'updated_at' => $now,
        ]);
    }

    private static function parseDate(?string $value): string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $th) {
            return Carbon::now()->format('Y-m-d');
        }
    }
}
