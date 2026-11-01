<?php

namespace App\Services;

use App\Models\MasterCabang;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Email hired-candidates ke user pembuat personnel request + opsional inspector.
 *
 * Inspector (dan CC tambahan): ATS_INSPECTOR_EMAILS=email1@...,email2@...
 * Kosong / tidak di-set → hanya user requester (dari master_karyawan).
 */
class AtsStakeholderEmailService
{
    private const EVENT_EMPLOYEE_MIGRATED = 'employee_migrated';
    private const EVENT_HIRED_VOID = 'hired_void';

    /**
     * @param object|array|null $recruitment
     * @param array<string, mixed> $migrationSummary
     */
    public function sendEmployeeMigrated($recruitment, array $migrationSummary, string $sentBy): void
    {
        $personnelRequest = $this->loadPersonnelRequest($recruitment);
        $recipients = $this->resolveRecipients($personnelRequest);
        if ($recipients === []) {
            return;
        }

        $candidateName = trim((string) ($migrationSummary['nama_kandidat'] ?? $this->candidateName($recruitment)));
        $posisi = trim((string) ($migrationSummary['posisi_jabatan'] ?? '-'));
        $subject = "[Pemberitahuan ATS] Kandidat Akan Bergabung — {$candidateName}" . ($posisi !== '-' ? " ({$posisi})" : '');

        $basePayload = (object) [
            'nama_kandidat' => $candidateName,
            'no_request' => $this->noRequest($personnelRequest),
            'posisi_jabatan' => $posisi,
            'nik_karyawan' => trim((string) ($migrationSummary['nik_karyawan'] ?? '-')),
            'tgl_mulai_kerja' => trim((string) ($migrationSummary['tgl_mulai_kerja_formatted'] ?? '-')),
            'status_karyawan' => trim((string) ($migrationSummary['status_karyawan'] ?? '-')),
            'cabang' => trim((string) ($migrationSummary['cabang'] ?? '-')),
            'departemen' => trim((string) ($migrationSummary['departemen'] ?? '-')),
            'processed_by' => trim((string) ($migrationSummary['migrated_by'] ?? $sentBy)),
            'processed_at' => trim((string) ($migrationSummary['migrated_at_formatted'] ?? '-')),
        ];

        foreach ($recipients as $recipient) {
            $payload = clone $basePayload;
            $payload->nama_user = $recipient['name'];

            $this->sendOne(
                $recipient['email'],
                $subject,
                GenerateMessageAtsEmail::bodyEmailEmployeeMigratedNotifStakeholder($payload),
                $sentBy,
                self::EVENT_EMPLOYEE_MIGRATED,
                $recruitment
            );
        }
    }

    /**
     * @param object|array|null $recruitment
     */
    public function sendHiredVoid($recruitment, string $reason, string $voidBy, string $sentBy): void
    {
        $personnelRequest = $this->loadPersonnelRequest($recruitment);
        $recipients = $this->resolveRecipients($personnelRequest);
        if ($recipients === []) {
            return;
        }

        $candidateName = $this->candidateName($recruitment);
        $posisi = $this->resolveAppliedPositionLabel($recruitment, $personnelRequest);
        $subject = "[Pemberitahuan ATS] Pembatalan Bergabung — {$candidateName}" . ($posisi !== '-' ? " ({$posisi})" : '');

        $basePayload = (object) [
            'nama_kandidat' => $candidateName,
            'no_request' => $this->noRequest($personnelRequest),
            'posisi' => $posisi,
            'void_reason' => trim($reason),
            'void_by' => trim($voidBy) ?: $sentBy,
            'void_at' => Carbon::now()->format('d M Y, H:i') . ' WIB',
        ];

        foreach ($recipients as $recipient) {
            $payload = clone $basePayload;
            $payload->nama_user = $recipient['name'];

            $this->sendOne(
                $recipient['email'],
                $subject,
                GenerateMessageAtsEmail::bodyEmailHiredVoidNotifStakeholder($payload),
                $sentBy,
                self::EVENT_HIRED_VOID,
                $recruitment
            );
        }
    }

    /**
     * Ringkasan label dari data migrasi (jabatan/cabang/dept dari master).
     *
     * @param array<string, mixed> $migrationSummary
     * @return array<string, mixed>
     */
    public function enrichMigrationSummary(array $migrationSummary): array
    {
        $jabatanId = (int) ($migrationSummary['id_jabatan'] ?? 0);
        $deptId = (int) ($migrationSummary['id_department'] ?? 0);
        $cabangId = (int) ($migrationSummary['id_cabang'] ?? 0);

        $migrationSummary['posisi_jabatan'] = $migrationSummary['posisi_jabatan']
            ?? $this->lookupJabatanName($jabatanId);
        $migrationSummary['departemen'] = $migrationSummary['departemen']
            ?? $this->lookupDepartmentName($deptId);
        $cabangLabel = trim((string) ($migrationSummary['cabang'] ?? ''));
        if ($cabangLabel === '') {
            $migrationSummary['cabang'] = $this->lookupCabangName($cabangId);
        } else {
            $migrationSummary['cabang'] = $cabangLabel;
        }

        $rawStart = $migrationSummary['tgl_mulai_kerja'] ?? null;
        $migrationSummary['tgl_mulai_kerja_formatted'] = $migrationSummary['tgl_mulai_kerja_formatted']
            ?? $this->formatDateId($rawStart);

        $rawAt = $migrationSummary['migrated_at'] ?? null;
        if ($rawAt && empty($migrationSummary['migrated_at_formatted'])) {
            try {
                $migrationSummary['migrated_at_formatted'] = Carbon::parse($rawAt)->format('d M Y, H:i') . ' WIB';
            } catch (\Throwable $e) {
                $migrationSummary['migrated_at_formatted'] = (string) $rawAt;
            }
        }

        return $migrationSummary;
    }

    /**
     * User pembuat PR + email dari ATS_INSPECTOR_EMAILS (jika ada).
     *
     * @return array<int, array{email: string, name: string}>
     */
    private function resolveRecipients($personnelRequest): array
    {
        $merged = [];
        $seenEmails = [];

        foreach ($this->resolvePersonnelRequestCreator($personnelRequest) as $recipient) {
            $emailKey = strtolower($recipient['email']);
            if (isset($seenEmails[$emailKey])) {
                continue;
            }
            $seenEmails[$emailKey] = true;
            $merged[] = $recipient;
        }

        foreach ($this->resolveInspectorEmailsFromEnv() as $recipient) {
            $emailKey = strtolower($recipient['email']);
            if (isset($seenEmails[$emailKey])) {
                continue;
            }
            $seenEmails[$emailKey] = true;
            $merged[] = $recipient;
        }

        return $merged;
    }

    /**
     * @return array<int, array{email: string, name: string}>
     */
    private function resolvePersonnelRequestCreator($personnelRequest): array
    {
        if (!$personnelRequest) {
            return [];
        }

        $name = trim((string) (is_object($personnelRequest)
            ? ($personnelRequest->created_by ?? '')
            : ($personnelRequest['created_by'] ?? '')));

        if ($name === '') {
            return [];
        }

        $mk = DB::table('master_karyawan')->where('nama_lengkap', $name)->first();
        $email = trim((string) ($mk->email ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [];
        }

        return [
            'email' => $email,
            'name' => $name,
        ]];
    }

    /**
     * @return array<int, array{email: string, name: string}>
     */
    private function resolveInspectorEmailsFromEnv(): array
    {
        $raw = trim((string) env('ATS_INSPECTOR_EMAILS', ''));
        if ($raw === '') {
            return [];
        }

        $out = [];
        foreach (preg_split('/[,;]+/', $raw) as $part) {
            $email = trim($part);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $out[] = [
                'email' => $email,
                'name' => $this->displayNameForEnvEmail($email),
            ];
        }

        return $out;
    }

    private function displayNameForEnvEmail(string $email): string
    {
        $local = strstr($email, '@', true);
        if ($local === false || $local === '') {
            return $email;
        }

        return ucwords(str_replace(['.', '_', '-'], ' ', $local));
    }

    private function sendOne(
        string $to,
        string $subject,
        string $body,
        string $sentBy,
        string $eventKey,
        $recruitment
    ): void {
        if ($body === '') {
            return;
        }

        try {
            SendEmail::where('to', $to)
                ->where('subject', $subject)
                ->where('body', $body)
                ->where('karyawan', 'Sistem ATS')
                ->noReply()
                ->send();
        } catch (\Throwable $exception) {
            Log::warning('ATS stakeholder email failed.', [
                'event' => $eventKey,
                'to' => $to,
                'recruitment_id' => is_object($recruitment) ? ($recruitment->id ?? null) : null,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function loadPersonnelRequest($recruitment)
    {
        $prId = null;
        if (is_object($recruitment)) {
            $prId = $recruitment->personnel_request_id ?? null;
        } elseif (is_array($recruitment)) {
            $prId = $recruitment['personnel_request_id'] ?? null;
        }

        if (!$prId) {
            return null;
        }

        return DB::table('personnel_requests')->where('id', $prId)->first();
    }

    private function candidateName($recruitment): string
    {
        if (is_object($recruitment)) {
            return trim((string) ($recruitment->nama_lengkap ?? 'Kandidat'));
        }
        if (is_array($recruitment)) {
            return trim((string) ($recruitment['nama_lengkap'] ?? 'Kandidat'));
        }

        return 'Kandidat';
    }

    private function noRequest($personnelRequest): string
    {
        if (!$personnelRequest) {
            return '-';
        }

        if (is_object($personnelRequest)) {
            return trim((string) ($personnelRequest->no_request ?? '-'));
        }

        return trim((string) ($personnelRequest['no_request'] ?? '-'));
    }

    private function resolveAppliedPositionLabel($recruitment, $personnelRequest): string
    {
        $dataObj = is_object($recruitment) ? $recruitment : (object) $recruitment;
        if ($personnelRequest && !isset($dataObj->personnelRequest)) {
            $dataObj->personnel_request_id = $personnelRequest->id ?? null;
        }

        return GenerateMessageAtsEmail::posisiLabelInternal($dataObj);
    }

    private function lookupJabatanName(int $id): string
    {
        if ($id <= 0) {
            return '-';
        }

        $name = DB::table('master_jabatan')->where('id', $id)->value('nama_jabatan');

        return trim((string) $name) !== '' ? trim((string) $name) : '-';
    }

    private function lookupDepartmentName(int $id): string
    {
        if ($id <= 0) {
            return '-';
        }

        $name = DB::table('master_divisi')->where('id', $id)->value('nama_divisi');

        return trim((string) $name) !== '' ? trim((string) $name) : '-';
    }

    private function lookupCabangName($id): string
    {
        if ($id === null || $id === '') {
            return '-';
        }

        if (!is_numeric($id)) {
            $byName = trim((string) $id);

            return $byName !== '' ? $byName : '-';
        }

        $cabangId = (int) $id;
        if ($cabangId <= 0) {
            return '-';
        }

        $row = MasterCabang::query()->find($cabangId);
        if (!$row) {
            return '-';
        }

        $name = trim((string) ($row->nama_cabang ?? ''));
        if ($name !== '') {
            return $name;
        }

        $code = trim((string) ($row->kode_cabang ?? ''));

        return $code !== '' ? $code : '-';
    }

    private function formatDateId($date): string
    {
        if ($date === null || trim((string) $date) === '') {
            return '-';
        }

        try {
            return Carbon::parse($date)->format('d M Y');
        } catch (\Throwable $e) {
            return trim((string) $date);
        }
    }
}
