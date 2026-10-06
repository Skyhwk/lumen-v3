<?php

namespace App\Support\Greatday;

use App\Models\Hr\HrRequest;
use Carbon\Carbon;

/**
 * Copy push/in-app Greatday — detail, profesional, tegas (hindari nada informal).
 */
class NotificationCopy
{
    /** Tab formulir hub: submission | approval | history */
    public static function pathForms($tab = null): string
    {
        if ($tab === null || $tab === '') {
            return '/forms';
        }

        $tab = (string) $tab;
        $allowed = ['submission', 'approval', 'history'];
        if (!in_array($tab, $allowed, true)) {
            return '/forms';
        }

        return '/forms?tab=' . $tab;
    }

    public static function pathHome(): string
    {
        return '/';
    }

    public static function pathNotifications(): string
    {
        return '/notifications';
    }

    public static function pathAttendance(): string
    {
        return '/attendance';
    }

    /**
     * @return array{title: string, body: string, url: string, kind: string, reminder_type: string}
     */
    public static function attendanceReminderMissingMasuk(string $tanggalYmd, ?string $jadwalMasuk = null): array
    {
        $jam = self::formatJamSingkat($jadwalMasuk) ?: '08:00';
        $hari = self::formatDate($tanggalYmd);

        return [
            'title' => 'Kehadiran · absen masuk belum tercatat',
            'body' => "Hingga saat ini absen masuk Anda pada {$hari} belum tercatat (jadwal masuk {$jam}). "
                . 'Segera lakukan absensi masuk melalui mesin atau aplikasi Attendance.',
            'url' => self::pathAttendance(),
            'kind' => 'attendance_reminder',
            'reminder_type' => 'missing_masuk',
        ];
    }

    /**
     * @return array{title: string, body: string, url: string, kind: string, reminder_type: string}
     */
    public static function attendanceReminderMissingPulang(string $tanggalYmd, ?string $jadwalPulang = null): array
    {
        $jam = self::formatJamSingkat($jadwalPulang) ?: '17:00';
        $hari = self::formatDate($tanggalYmd);

        return [
            'title' => 'Kehadiran · absen pulang belum tercatat',
            'body' => "Hingga saat ini absen pulang Anda pada {$hari} belum tercatat (jadwal pulang {$jam}). "
                . 'Segera lakukan absensi pulang melalui mesin atau aplikasi Attendance.',
            'url' => self::pathAttendance(),
            'kind' => 'attendance_reminder',
            'reminder_type' => 'missing_pulang',
        ];
    }

    private static function formatJamSingkat(?string $time): string
    {
        $time = trim((string) $time);
        if ($time === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '';
    }

    /** Tab hub formulir dari teks notifikasi (untuk URL lama tanpa ?tab=). */
    public static function inferFormsTabFromCopy($title, $body): string
    {
        $hay = strtolower(trim((string) $title . ' ' . (string) $body));

        if (
            strpos($hay, 'menunggu persetujuan') !== false
            || strpos($hay, 'menunggu review') !== false
            || strpos($hay, 'akan segera mereview') !== false
            || strpos($hay, 'diteruskan ke hrd') !== false
            || strpos($hay, 'diteruskan kepada hrd') !== false
            || strpos($hay, 'penugasan lembur') !== false
        ) {
            return 'approval';
        }

        return 'submission';
    }

    /**
     * Redirect notifikasi lama (/forms/leaveRequests, dll.) ke hub formulir.
     */
    public static function normalizeAppUrl($url, $title = '', $body = ''): string
    {
        $path = trim((string) $url);
        if ($path === '') {
            return self::pathForms(self::inferFormsTabFromCopy($title, $body));
        }

        if ($path === '/forms' || strpos($path, '/forms/') === 0) {
            $tab = self::inferFormsTabFromCopy($title, $body);
            if (strpos($path, '?tab=') !== false) {
                return $path;
            }

            return self::pathForms($tab);
        }

        return $path;
    }

    public static function documentLabel(string $requestType): string
    {
        switch ($requestType) {
            case HrRequest::TYPE_LEAVE:
                return 'permohonan cuti';
            case HrRequest::TYPE_PERMISSION:
                return 'permohonan izin';
            case HrRequest::TYPE_OVERTIME:
                return 'permohonan lembur';
            case HrRequest::TYPE_ATTENDANCE_CORRECTION:
                return 'koreksi kehadiran';
            case HrRequest::TYPE_CONSULTATION:
                return 'permohonan konsultasi';
            case HrRequest::TYPE_EVENT_REPORT:
                return 'laporan kegiatan';
            default:
                return 'pengajuan HR';
        }
    }

    public static function documentLabelTitleCase(string $requestType): string
    {
        switch ($requestType) {
            case HrRequest::TYPE_LEAVE:
                return 'Permohonan cuti';
            case HrRequest::TYPE_PERMISSION:
                return 'Permohonan izin';
            case HrRequest::TYPE_OVERTIME:
                return 'Permohonan lembur';
            case HrRequest::TYPE_ATTENDANCE_CORRECTION:
                return 'Koreksi kehadiran';
            case HrRequest::TYPE_CONSULTATION:
                return 'Permohonan konsultasi';
            case HrRequest::TYPE_EVENT_REPORT:
                return 'Laporan kegiatan';
            default:
                return 'Pengajuan HR';
        }
    }

    public static function detailLine(HrRequest $request): string
    {
        $request->loadMissing([
            'leaveDetail',
            'permissionDetail',
            'overtimeDetail',
            'attendanceCorrectionDetail',
        ]);

        switch ($request->request_type) {
            case HrRequest::TYPE_LEAVE:
                $d = $request->leaveDetail;
                if (!$d) {
                    break;
                }
                $kind = self::leaveKindLabel($d->leave_kind ?? null);
                $range = self::formatDateRange($d->start_date ?? null, $d->end_date ?? null);
                $parts = array_filter([$kind, $range]);
                if ($request->no_document) {
                    $parts[] = 'No. ' . $request->no_document;
                }

                return implode(' · ', $parts);

            case HrRequest::TYPE_PERMISSION:
                $d = $request->permissionDetail;
                if (!$d) {
                    break;
                }
                $range = self::formatDateRange($d->start_date ?? null, $d->end_date ?? null);
                $kind = self::permissionKindLabel($d->permission_kind ?? null);
                $parts = array_filter([$kind, $range]);

                return implode(' · ', $parts);

            case HrRequest::TYPE_OVERTIME:
                $d = $request->overtimeDetail;
                if (!$d) {
                    break;
                }
                $range = self::formatDateRange($d->start_date ?? null, $d->end_date ?? null);
                $time = '';
                if (!empty($d->start_time) && !empty($d->end_time)) {
                    $time = substr((string) $d->start_time, 0, 5) . '–' . substr((string) $d->end_time, 0, 5);
                }
                $parts = array_filter([$range, $time]);

                return implode(' · ', $parts);

            case HrRequest::TYPE_ATTENDANCE_CORRECTION:
                $d = $request->attendanceCorrectionDetail;
                if ($d && !empty($d->correction_date)) {
                    return 'Tanggal ' . self::formatDate($d->correction_date);
                }
                break;
        }

        if ($request->no_document) {
            return 'No. dokumen ' . $request->no_document;
        }

        return '';
    }

    /** Konfirmasi ke pengaju setelah submit */
    public static function submissionAcknowledged(HrRequest $request, string $url, bool $requiresAtasanApproval): array
    {
        $doc = self::documentLabelTitleCase($request->request_type);
        $detail = self::detailLine($request);
        $detailSentence = $detail !== '' ? " ({$detail})" : '';

        if ($requiresAtasanApproval) {
            $body = "{$doc}{$detailSentence} telah diterima sistem. Atasan Anda akan segera mereview pengajuan ini. Pantau status di Greatday → Formulir.";
        } else {
            $body = "{$doc}{$detailSentence} telah diterima sistem dan diteruskan ke HRD untuk proses selanjutnya. Pantau status di Greatday → Formulir.";
        }

        return [
            'title' => "{$doc} · berhasil diajukan",
            'body' => $body,
            'url' => $url,
        ];
    }

    /** @return array{title: string, body: string, url: string} */
    public static function atasanPending(HrRequest $request, string $submitterName, string $url): array
    {
        $doc = self::documentLabelTitleCase($request->request_type);
        $who = self::personName($submitterName);
        $detail = self::detailLine($request);
        $detailSentence = $detail !== '' ? " Detail pengajuan: {$detail}." : '';

        return [
            'title' => "{$doc} · perlu persetujuan Anda",
            'body' => "{$who} mengajukan {$doc}.{$detailSentence} Pengajuan ini memerlukan persetujuan Anda. Tinjau segera di Greatday → Formulir, tab Persetujuan.",
            'url' => $url,
        ];
    }

    /** @return array{title: string, body: string, url: string} */
    public static function forwardToHrd(HrRequest $request, string $url, ?string $divisionName = null): array
    {
        $doc = self::documentLabelTitleCase($request->request_type);
        $who = self::personName($request->created_by_name);
        $detail = self::detailLine($request);
        $detailSentence = $detail !== '' ? " Detail: {$detail}." : '';
        $div = $divisionName ? " (divisi {$divisionName})" : '';

        return [
            'title' => "{$doc} · antrian HRD",
            'body' => "Pengajuan {$doc} dari {$who}{$div} telah disetujui atasan dan menunggu proses HRD.{$detailSentence} Periksa di Greatday → Formulir.",
            'url' => $url,
        ];
    }

    /** @return array{title: string, body: string, url: string} */
    public static function rejectedByAtasan(
        HrRequest $request,
        string $approverName,
        ?string $reason,
        string $url
    ): array {
        $doc = self::documentLabelTitleCase($request->request_type);
        $by = self::personName($approverName);
        $why = self::reasonText($reason);
        $detail = self::detailLine($request);
        $detailSentence = $detail !== '' ? " Data pengajuan: {$detail}." : '';

        return [
            'title' => "{$doc} · belum disetujui atasan",
            'body' => "{$doc} Anda belum disetujui oleh atasan ({$by}).{$detailSentence} Catatan peninjau: {$why} Hubungi atasan Anda apabila memerlukan klarifikasi.",
            'url' => $url,
        ];
    }

    /** @return array{title: string, body: string, url: string} */
    public static function overtimeParticipantNotified(HrRequest $request, string $creatorName, string $url): array
    {
        $who = self::personName($creatorName);
        $detail = self::detailLine($request);
        $detailSentence = $detail !== '' ? " Jadwal: {$detail}." : '';

        return [
            'title' => 'Penugasan lembur',
            'body' => "{$who} mencantumkan Anda dalam pengajuan lembur.{$detailSentence} Detail lengkap tersedia di Greatday → Formulir. Apabila jadwal bentrok, segera koordinasikan dengan pengaju.",
            'url' => $url,
        ];
    }

    public static function legacyAtasanPending(string $docTitle, string $submitterName, string $url, ?string $detail = null): array
    {
        $who = self::personName($submitterName);
        $detailSentence = $detail ? " Detail pengajuan: {$detail}." : '';

        return [
            'title' => "{$docTitle} · perlu persetujuan Anda",
            'body' => "{$who} mengajukan {$docTitle}.{$detailSentence} Pengajuan ini memerlukan persetujuan Anda. Tinjau segera di Greatday → Formulir, tab Persetujuan.",
            'url' => $url,
        ];
    }

    public static function legacyForwardToHrd(string $docTitle, string $submitterName, string $url, ?string $detail = null): array
    {
        $who = self::personName($submitterName);
        $detailSentence = $detail ? " Detail: {$detail}." : '';

        return [
            'title' => "{$docTitle} · antrian HRD",
            'body' => "Pengajuan {$docTitle} dari {$who} telah disetujui atasan dan menunggu proses HRD.{$detailSentence}",
            'url' => $url,
        ];
    }

    public static function legacyRejected(string $docTitle, string $approverName, ?string $reason, string $url): array
    {
        $by = self::personName($approverName);
        $why = self::reasonText($reason);

        return [
            'title' => "{$docTitle} · belum disetujui",
            'body' => "{$docTitle} Anda belum disetujui oleh atasan ({$by}). Catatan peninjau: {$why} Hubungi atasan Anda apabila memerlukan klarifikasi.",
            'url' => $url,
        ];
    }

    public static function consultationSubmitted(string $employeeName, string $url): array
    {
        $who = self::personName($employeeName);

        return [
            'title' => 'Konsultasi HR · pengajuan baru',
            'body' => "{$who} mengajukan permohonan konsultasi HR. HRD akan segera meninjau topik dan jadwal yang diajukan. Periksa detail di Greatday → Formulir.",
            'url' => $url,
        ];
    }

    public static function consultationApproved(string $url): array
    {
        return [
            'title' => 'Konsultasi HR · disetujui',
            'body' => 'Permohonan konsultasi Anda telah disetujui HRD. Detail jadwal dan catatan tersedia di Greatday → Formulir.',
            'url' => $url,
        ];
    }

    public static function consultationRejected(?string $reason, string $url): array
    {
        return [
            'title' => 'Konsultasi HR · belum disetujui',
            'body' => 'Permohonan konsultasi belum dapat diproses. Catatan HRD: ' . self::reasonText($reason) . ' Ajukan ulang atau hubungi HRD untuk penjelasan.',
            'url' => $url,
        ];
    }

    public static function reimbursementSubmitted(string $submitterName, string $url): array
    {
        $who = self::personName($submitterName);

        return [
            'title' => 'Reimburse lembur · perlu persetujuan',
            'body' => "{$who} mengajukan penggantian biaya lembur. Atasan berwenang akan segera mereview dokumen dan rincian biaya di Greatday → Formulir.",
            'url' => $url,
        ];
    }

    public static function reimbursementApproved(string $url): array
    {
        return [
            'title' => 'Reimburse lembur · disetujui',
            'body' => 'Penggantian biaya lembur Anda telah disetujui. Pembayaran mengikuti jadwal payroll perusahaan. Pantau status di Greatday → Formulir.',
            'url' => $url,
        ];
    }

    public static function reimbursementRejected(string $stage, ?string $reason, string $url): array
    {
        $why = self::reasonText($reason);

        return [
            'title' => 'Reimburse lembur · belum disetujui',
            'body' => "Pengajuan reimburse lembur belum disetujui pada tahap {$stage}. Catatan peninjau: {$why} Perbaiki dokumen atau hubungi peninjau terkait.",
            'url' => $url,
        ];
    }

    private static function personName(?string $name): string
    {
        $n = trim((string) $name);

        return $n !== '' ? $n : 'Karyawan';
    }

    private static function reasonText(?string $reason): string
    {
        $r = trim((string) $reason);

        return $r !== '' ? $r : 'Tidak ada catatan tambahan dari peninjau.';
    }

    private static function permissionKindLabel(?string $kind): string
    {
        switch ($kind) {
            case 'sick':
                return 'Izin sakit';
            case 'late':
                return 'Izin terlambat';
            default:
                return 'Izin';
        }
    }

    private static function leaveKindLabel(?string $kind): string
    {
        switch ($kind) {
            case 'special':
                return 'Cuti khusus';
            case 'unpaid':
                return 'Cuti tanpa gaji';
            case 'phl':
                return 'Pengganti hari libur';
            case 'urgent':
                return 'Cuti mendesak';
            default:
                return 'Cuti tahunan';
        }
    }

    private static function formatDate($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->locale('id')->translatedFormat('d M Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private static function formatDateRange($start, $end): string
    {
        $s = self::formatDate($start);
        $e = self::formatDate($end);
        if ($s === '' && $e === '') {
            return '';
        }
        if ($s === $e || $e === '') {
            return $s;
        }
        if ($s === '') {
            return $e;
        }

        return "{$s} – {$e}";
    }
}
