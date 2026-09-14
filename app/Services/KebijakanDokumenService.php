<?php

namespace App\Services;

use App\Models\DraftingKebijakan;
use App\Models\KebijakanDokumen;
use App\Models\MasterKaryawan;
use App\Models\RequestKebijakan;
use App\Models\RequestKebijakanVerifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KebijakanDokumenService
{
    public const MANUAL_UPDATE_ALLOWED_KARYAWAN_ID = 127;

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
        $legalDefaults = RequestKebijakanRevisionService::resolveLegalFinalDefaults($record);
        $terbitan = max(1, (int) ($metadata['terbitan'] ?? $metadata['cetakan'] ?? $legalDefaults['terbitan']));
        $revisian = max(0, (int) ($metadata['revisian'] ?? $metadata['revisi'] ?? $legalDefaults['revisian']));
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
        $kategori = strtolower((string) ($record->kategori ?? 'new'));
        $noDokumen = self::resolveNoDokumenForRequest($record, $kategori, $parentDokumenId);

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
                'no_dokumen' => $noDokumen,
                'judul' => $record->judul ?? $record->drafting->judul,
                'kategori' => $kategori,
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

    public static function isProgrammer($employee): bool
    {
        if (!$employee) {
            return false;
        }

        return in_array((int) ($employee->id_jabatan ?? 0), [41, 42], true);
    }

    public static function isDepartmentSeven($employee): bool
    {
        if (!$employee) {
            return false;
        }

        return (int) ($employee->id_department ?? 0) === 7;
    }

    public static function canManualCreate($employee): bool
    {
        if (!$employee) {
            return false;
        }

        if (self::isProgrammer($employee)) {
            return true;
        }

        if (!self::isDepartmentSeven($employee)) {
            return false;
        }

        return RequestKebijakanNotificationService::employeeHasMenuAccess(
            $employee,
            RequestKebijakanNotificationService::URL_DOKUMEN,
            ['create']
        );
    }

    public static function getManualCreateOptions(): array
    {
        return [
            'verifier_candidates' => RequestKebijakanVerifierService::getVerifierCandidates(),
            'director_candidates' => self::getDirectorCandidates(),
        ];
    }

    public static function isManualDocument(KebijakanDokumen $dokumen): bool
    {
        if ((bool) ($dokumen->is_manual ?? false)) {
            return true;
        }

        $dokumen->loadMissing('requestKebijakan');
        $catatan = (string) optional($dokumen->requestKebijakan)->catatan;

        return str_starts_with($catatan, 'Dibuat manual oleh ');
    }

    public static function canUpdateManualDocument($employee, KebijakanDokumen $dokumen): bool
    {
        if (!self::isManualDocument($dokumen) || $dokumen->status !== 'active' || !$dokumen->is_active) {
            return false;
        }

        if (!$employee || empty($employee->id)) {
            return false;
        }

        $employeeId = (int) $employee->id;

        if ($employeeId === self::MANUAL_UPDATE_ALLOWED_KARYAWAN_ID) {
            return true;
        }

        if ((int) ($dokumen->manual_created_by ?? 0) === $employeeId) {
            return true;
        }

        $dokumen->loadMissing('requestKebijakan');
        $requestBy = trim((string) optional($dokumen->requestKebijakan)->request_by);
        $employeeName = trim((string) ($employee->nama_lengkap ?? ''));

        return $requestBy !== '' && $requestBy === $employeeName;
    }

    public static function buildManualEditPayload(KebijakanDokumen $dokumen): array
    {
        $dokumen->loadMissing(['drafting', 'requestKebijakan']);

        $draft = $dokumen->drafting;
        $request = $dokumen->requestKebijakan;

        $verifierIds = RequestKebijakanVerifier::query()
            ->where('request_kebijakan_id', $dokumen->request_kebijakan_id)
            ->where('is_active', true)
            ->where('status', 'verified')
            ->orderBy('id')
            ->pluck('verifier_karyawan_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $firstVerifier = RequestKebijakanVerifier::query()
            ->where('request_kebijakan_id', $dokumen->request_kebijakan_id)
            ->where('is_active', true)
            ->where('status', 'verified')
            ->orderBy('id')
            ->first();

        return [
            'id' => $dokumen->id,
            'no_dokumen' => $dokumen->no_dokumen,
            'revisian' => $dokumen->revisian,
            'terbitan' => $dokumen->cetakan,
            'tanggal_terbit' => self::formatDateInput($dokumen->tanggal_pengesahan),
            'tanggal_verifikasi_reviewer' => self::formatDateInput(
                optional($firstVerifier)->verification_date ?? optional($request)->user_reviewed_at
            ),
            'tanggal_verifikasi_legal' => self::formatDateInput($dokumen->legal_verified_at),
            'divisi_bagian' => optional($draft)->divisi_bagian ?? '',
            'judul' => $dokumen->judul,
            'tujuan' => optional($draft)->tujuan ?? optional($request)->tujuan ?? '',
            'ruang_lingkup' => optional($draft)->ruang_lingkup ?? optional($request)->ruang_lingkup ?? '',
            'definisi' => optional($draft)->definisi ?? optional($request)->definisi ?? '',
            'isi_ketetapan' => optional($draft)->isi_ketetapan ?? optional($request)->isi_ketetapan ?? '',
            'catatan_legal' => optional($draft)->catatan_legal ?? '',
            'verifier_ids' => $verifierIds,
            'legal_verified_karyawan_id' => self::resolveKaryawanIdByName($dokumen->legal_verified_by),
            'director_karyawan_id' => self::resolveKaryawanIdByName($dokumen->director_approved_by),
        ];
    }

    public static function updateManualActiveDocument($employee, int $dokumenId, array $payload): KebijakanDokumen
    {
        $dokumen = KebijakanDokumen::with(['drafting', 'requestKebijakan'])->findOrFail($dokumenId);

        if (!self::canUpdateManualDocument($employee, $dokumen)) {
            throw new \RuntimeException('Anda tidak memiliki akses mengubah ketetapan manual ini');
        }

        $parsed = self::parseManualPayload($payload, (int) $dokumen->id);
        $by = $employee->nama_lengkap ?? 'System';
        $now = Carbon::now();

        $request = $dokumen->requestKebijakan;
        $draft = $dokumen->drafting;

        if (!$request || !$draft) {
            throw new \RuntimeException('Data request atau draft tidak ditemukan');
        }

        RequestKebijakanVerifier::query()
            ->where('request_kebijakan_id', $request->id)
            ->delete();

        self::syncManualVerifiers(
            $request,
            $parsed['verifier_employees'],
            $parsed['tanggal_verifikasi_reviewer'],
            $by,
            $parsed['reviewer_verified_at']
        );

        $request->update([
            'judul' => $parsed['judul'],
            'tujuan' => $parsed['tujuan'],
            'ruang_lingkup' => $parsed['ruang_lingkup'],
            'definisi' => $parsed['definisi'],
            'isi_ketetapan' => $parsed['isi_ketetapan'],
            'user_reviewed_by' => $parsed['verifier_names'],
            'user_reviewed_at' => $parsed['reviewer_verified_at'],
            'reviewed_by' => $parsed['legal_verified_by'],
            'reviewed_at' => $parsed['legal_verified_at'],
        ]);

        $draft->update([
            'divisi_bagian' => $parsed['divisi_bagian'],
            'judul' => $parsed['judul'],
            'tujuan' => $parsed['tujuan'],
            'ruang_lingkup' => $parsed['ruang_lingkup'],
            'definisi' => $parsed['definisi'],
            'isi_ketetapan' => $parsed['isi_ketetapan'],
            'catatan_legal' => $parsed['catatan_legal'],
            'updated_by' => $by,
            'updated_at' => $now,
        ]);

        $dokumen->update([
            'no_dokumen' => $parsed['no_dokumen'],
            'judul' => $parsed['judul'],
            'revisian' => $parsed['revisian'],
            'cetakan' => $parsed['cetakan'],
            'tanggal_pengesahan' => $parsed['tanggal_pengesahan'],
            'legal_verified_by' => $parsed['legal_verified_by'],
            'legal_verified_at' => $parsed['legal_verified_at'],
            'director_approved_by' => $parsed['director_approved_by'],
            'director_approved_at' => $parsed['director_approved_at'],
            'updated_at' => $now,
        ]);

        KebijakanDokumenQrService::syncAndStore($dokumen->fresh(), $by);

        return $dokumen->fresh(['drafting', 'requestKebijakan']);
    }

    public static function getDirectorCandidates(): array
    {
        return MasterKaryawan::query()
            ->with('jabatan')
            ->where('is_active', 1)
            ->whereIn(DB::raw('UPPER(REPLACE(grade, "_", " "))'), ['DIRECTOR'])
            ->orderBy('nama_lengkap')
            ->get()
            ->map(function ($karyawan) {
                return [
                    'id' => $karyawan->id,
                    'nama_lengkap' => $karyawan->nama_lengkap,
                    'jabatan' => KaryawanProfileService::resolveJabatan($karyawan),
                    'grade' => RequestKebijakanWorkflowService::normalizeGrade($karyawan->grade ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    public static function createManualActiveDocument($employee, array $payload): KebijakanDokumen
    {
        if (!self::canManualCreate($employee)) {
            throw new \RuntimeException('Anda tidak memiliki akses membuat ketetapan manual');
        }

        $parsed = self::parseManualPayload($payload);
        $by = $employee->nama_lengkap ?? 'System';
        $now = Carbon::now();

        $request = RequestKebijakan::create([
            'no_request' => self::generateManualNoRequest(),
            'kategori' => 'new',
            'judul' => $parsed['judul'],
            'tujuan' => $parsed['tujuan'],
            'ruang_lingkup' => $parsed['ruang_lingkup'],
            'definisi' => $parsed['definisi'],
            'isi_ketetapan' => $parsed['isi_ketetapan'],
            'catatan' => 'Dibuat manual oleh ' . $by,
            'status' => 'completed',
            'request_by' => $by,
            'request_at' => $now,
            'approval_by' => $by,
            'approval_at' => $now,
            'processed_by' => $by,
            'processed_at' => $now,
            'forwarded_to_user_by' => $parsed['verifier_names'] ? $by : null,
            'forwarded_to_user_at' => $parsed['reviewer_verified_at'],
            'user_reviewed_by' => $parsed['verifier_names'],
            'user_reviewed_at' => $parsed['reviewer_verified_at'],
            'reviewed_by' => $parsed['legal_verified_by'],
            'reviewed_at' => $parsed['legal_verified_at'],
            'is_active' => true,
        ]);

        self::syncManualVerifiers(
            $request,
            $parsed['verifier_employees'],
            $parsed['tanggal_verifikasi_reviewer'],
            $by,
            $parsed['reviewer_verified_at']
        );

        $draft = DraftingKebijakan::create([
            'request_kebijakan_id' => $request->id,
            'divisi_bagian' => $parsed['divisi_bagian'],
            'judul' => $parsed['judul'],
            'tujuan' => $parsed['tujuan'],
            'ruang_lingkup' => $parsed['ruang_lingkup'],
            'definisi' => $parsed['definisi'],
            'isi_ketetapan' => $parsed['isi_ketetapan'],
            'catatan_legal' => $parsed['catatan_legal'],
            'status' => 'submitted',
            'processed_by' => $by,
            'processed_at' => $now,
            'submitted_by' => $by,
            'submitted_at' => $now,
        ]);

        $dokumen = KebijakanDokumen::create([
            'request_kebijakan_id' => $request->id,
            'drafting_kebijakan_id' => $draft->id,
            'no_dokumen' => $parsed['no_dokumen'],
            'judul' => $parsed['judul'],
            'kategori' => 'new',
            'revisian' => $parsed['revisian'],
            'cetakan' => $parsed['cetakan'],
            'tanggal_pengesahan' => $parsed['tanggal_pengesahan'],
            'status' => 'active',
            'legal_verified_by' => $parsed['legal_verified_by'],
            'legal_verified_at' => $parsed['legal_verified_at'],
            'director_approved_by' => $parsed['director_approved_by'],
            'director_approved_at' => $parsed['director_approved_at'],
            'is_active' => true,
            'is_manual' => true,
            'manual_created_by' => (int) ($employee->id ?? 0) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        KebijakanDokumenQrService::syncAndStore($dokumen->fresh(), $by);

        return $dokumen->fresh(['drafting', 'requestKebijakan']);
    }

    private static function parseManualPayload(array $payload, ?int $excludeDokumenId = null): array
    {
        $now = Carbon::now();

        $divisiBagian = trim((string) ($payload['divisi_bagian'] ?? ''));

        if ($divisiBagian === '') {
            throw new \RuntimeException('Divisi / bagian wajib diisi');
        }

        $judul = trim((string) ($payload['judul'] ?? ''));

        if ($judul === '') {
            throw new \RuntimeException('Judul wajib diisi');
        }

        $isiKetetapan = trim(strip_tags((string) ($payload['isi_ketetapan'] ?? '')));

        if ($isiKetetapan === '') {
            throw new \RuntimeException('Isi ketetapan wajib diisi');
        }

        $noDokumen = trim((string) ($payload['no_dokumen'] ?? ''));

        if ($noDokumen === '') {
            throw new \RuntimeException('Nomor dokumen wajib diisi manual');
        }

        $revisian = max(0, (int) ($payload['revisian'] ?? $payload['revisi'] ?? 0));
        $cetakan = max(1, (int) ($payload['terbitan'] ?? $payload['cetakan'] ?? 1));
        $tanggalPengesahan = self::parseDate(
            $payload['tanggal_terbit'] ?? $payload['tanggal_pengesahan'] ?? $now->format('Y-m-d')
        );

        $duplicateQuery = KebijakanDokumen::query()
            ->where('no_dokumen', $noDokumen)
            ->where('revisian', $revisian)
            ->where('is_active', true);

        if ($excludeDokumenId) {
            $duplicateQuery->where('id', '!=', $excludeDokumenId);
        }

        if ($duplicateQuery->exists()) {
            throw new \RuntimeException("Dokumen {$noDokumen} revisi {$revisian} sudah ada");
        }

        $verifierIds = self::normalizeIdList($payload['verifier_ids'] ?? []);

        $legalVerifier = self::resolveManualKaryawan(
            (int) ($payload['legal_verified_karyawan_id'] ?? 0),
            RequestKebijakanVerifierService::VERIFIER_GRADES,
            'Verifikator final legal tidak valid'
        );

        $director = self::resolveManualKaryawan(
            (int) ($payload['director_karyawan_id'] ?? 0),
            ['DIRECTOR'],
            'Director pengesah tidak valid'
        );

        $verifierEmployees = empty($verifierIds)
            ? collect()
            : self::resolveManualVerifiers($verifierIds);

        $tanggalVerifikasiReviewer = empty($verifierIds)
            ? null
            : self::parseDate($payload['tanggal_verifikasi_reviewer'] ?? $tanggalPengesahan);
        $tanggalVerifikasiLegal = self::parseDate(
            $payload['tanggal_verifikasi_legal'] ?? $tanggalPengesahan
        );

        return [
            'divisi_bagian' => $divisiBagian,
            'judul' => $judul,
            'tujuan' => (string) ($payload['tujuan'] ?? ''),
            'ruang_lingkup' => (string) ($payload['ruang_lingkup'] ?? ''),
            'definisi' => (string) ($payload['definisi'] ?? ''),
            'isi_ketetapan' => (string) ($payload['isi_ketetapan'] ?? ''),
            'catatan_legal' => (string) ($payload['catatan_legal'] ?? '') ?: null,
            'no_dokumen' => $noDokumen,
            'revisian' => $revisian,
            'cetakan' => $cetakan,
            'tanggal_pengesahan' => $tanggalPengesahan,
            'tanggal_verifikasi_reviewer' => $tanggalVerifikasiReviewer,
            'reviewer_verified_at' => $tanggalVerifikasiReviewer
                ? Carbon::parse($tanggalVerifikasiReviewer)->startOfDay()
                : null,
            'legal_verified_at' => Carbon::parse($tanggalVerifikasiLegal)->startOfDay(),
            'director_approved_at' => Carbon::parse($tanggalPengesahan)->startOfDay(),
            'legal_verified_by' => $legalVerifier->nama_lengkap,
            'director_approved_by' => $director->nama_lengkap,
            'verifier_employees' => $verifierEmployees,
            'verifier_names' => $verifierEmployees->isEmpty()
                ? null
                : $verifierEmployees->pluck('nama_lengkap')->implode(', '),
        ];
    }

    private static function formatDateInput($value): string
    {
        if (!$value) {
            return date('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $th) {
            return date('Y-m-d');
        }
    }

    private static function resolveKaryawanIdByName(?string $namaLengkap): ?int
    {
        $namaLengkap = trim((string) $namaLengkap);

        if ($namaLengkap === '') {
            return null;
        }

        $id = MasterKaryawan::query()
            ->where('is_active', 1)
            ->where('nama_lengkap', $namaLengkap)
            ->value('id');

        return $id ? (int) $id : null;
    }

    private static function normalizeIdList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $value))));
    }

    private static function resolveManualKaryawan(int $karyawanId, array $allowedGrades, string $errorMessage): MasterKaryawan
    {
        if ($karyawanId <= 0) {
            throw new \RuntimeException($errorMessage);
        }

        $karyawan = MasterKaryawan::with('jabatan')
            ->where('is_active', 1)
            ->find($karyawanId);

        if (!$karyawan) {
            throw new \RuntimeException($errorMessage);
        }

        $grade = RequestKebijakanWorkflowService::normalizeGrade($karyawan->grade ?? '');

        if (!in_array($grade, $allowedGrades, true)) {
            throw new \RuntimeException($errorMessage);
        }

        return $karyawan;
    }

    private static function resolveManualVerifiers(array $verifierIds)
    {
        if (empty($verifierIds)) {
            return collect();
        }

        $employees = MasterKaryawan::with('jabatan')
            ->where('is_active', 1)
            ->whereIn('id', $verifierIds)
            ->get()
            ->keyBy('id');

        $ordered = collect();

        foreach ($verifierIds as $verifierId) {
            if (!$employees->has($verifierId)) {
                throw new \RuntimeException('Reviewer verifikasi tidak valid');
            }

            $employee = $employees->get($verifierId);
            $grade = RequestKebijakanWorkflowService::normalizeGrade($employee->grade ?? '');

            if (!in_array($grade, RequestKebijakanVerifierService::VERIFIER_GRADES, true)) {
                throw new \RuntimeException('Reviewer verifikasi harus bergrade Manager, Senior Manager, atau Director');
            }

            $ordered->push($employee);
        }

        return $ordered;
    }

    private static function syncManualVerifiers(
        RequestKebijakan $request,
        $verifierEmployees,
        ?string $verificationDate,
        string $assignedBy,
        ?Carbon $verifiedAt
    ): void {
        if ($verifierEmployees->isEmpty() || !$verificationDate || !$verifiedAt) {
            return;
        }

        foreach ($verifierEmployees as $employee) {
            RequestKebijakanVerifier::create([
                'request_kebijakan_id' => $request->id,
                'verifier_karyawan_id' => $employee->id,
                'verifier_nama_lengkap' => $employee->nama_lengkap,
                'verifier_jabatan' => KaryawanProfileService::resolveJabatan($employee),
                'assigned_by' => $assignedBy,
                'assigned_at' => $verifiedAt,
                'status' => 'verified',
                'verification_date' => $verificationDate,
                'verified_at' => $verifiedAt,
                'verification_round' => 1,
                'is_active' => true,
                'created_at' => $verifiedAt,
                'updated_at' => $verifiedAt,
            ]);
        }
    }

    private static function generateManualNoRequest(): string
    {
        do {
            $noRequest = str_replace('.', '/', (string) microtime(true));
        } while (RequestKebijakan::where('no_request', $noRequest)->exists());

        return $noRequest;
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

    public static function applyGlobalSearchToQuery($query, ?string $keyword)
    {
        $keyword = trim((string) $keyword);

        if ($keyword === '') {
            return $query;
        }

        return $query->where(function ($sub) use ($keyword) {
            $sub->where('kebijakan_dokumen.no_dokumen', 'like', '%' . $keyword . '%')
                ->orWhere('kebijakan_dokumen.judul', 'like', '%' . $keyword . '%')
                ->orWhere('kebijakan_dokumen.revisian', 'like', '%' . $keyword . '%')
                ->orWhere('kebijakan_dokumen.cetakan', 'like', '%' . $keyword . '%')
                ->orWhere('kebijakan_dokumen.tanggal_pengesahan', 'like', '%' . $keyword . '%')
                ->orWhere('kebijakan_dokumen.archived_at', 'like', '%' . $keyword . '%')
                ->orWhereHas('drafting', function ($draft) use ($keyword) {
                    $draft->where('divisi_bagian', 'like', '%' . $keyword . '%')
                        ->orWhere('judul', 'like', '%' . $keyword . '%')
                        ->orWhere('tujuan', 'like', '%' . $keyword . '%')
                        ->orWhere('ruang_lingkup', 'like', '%' . $keyword . '%')
                        ->orWhere('definisi', 'like', '%' . $keyword . '%')
                        ->orWhere('isi_ketetapan', 'like', '%' . $keyword . '%')
                        ->orWhere('catatan_legal', 'like', '%' . $keyword . '%');
                })
                ->orWhereHas('requestKebijakan', function ($request) use ($keyword) {
                    $request->where('request_by', 'like', '%' . $keyword . '%')
                        ->orWhere('no_request', 'like', '%' . $keyword . '%')
                        ->orWhere('judul', 'like', '%' . $keyword . '%')
                        ->orWhere('tujuan', 'like', '%' . $keyword . '%')
                        ->orWhere('ruang_lingkup', 'like', '%' . $keyword . '%')
                        ->orWhere('definisi', 'like', '%' . $keyword . '%')
                        ->orWhere('isi_ketetapan', 'like', '%' . $keyword . '%');
                });

            foreach (self::getArchiveReasonFilterMap() as $dbValue => $label) {
                if (stripos($label, $keyword) !== false || stripos($dbValue, $keyword) !== false) {
                    $sub->orWhere('kebijakan_dokumen.archive_reason', $dbValue);
                }
            }
        });
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

    private static function resolveNoDokumenForRequest(RequestKebijakan $record, string $kategori, ?int $parentDokumenId): string
    {
        if ($kategori === 'revision') {
            $parentNo = RequestKebijakanRevisionService::resolveParentNoDokumen($record);

            if ($parentNo) {
                return $parentNo;
            }
        }

        if ($kategori === 'revision' && $parentDokumenId) {
            $parent = KebijakanDokumen::find($parentDokumenId);

            if ($parent && $parent->no_dokumen) {
                return $parent->no_dokumen;
            }
        }

        return self::generateNoDokumen();
    }
}
