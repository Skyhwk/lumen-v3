<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AtsNotificationService
{
    public const URL_PERSONNEL_REQUEST = '/request/personnel-request';
    public const URL_DATA_PERSONNEL_REQUEST = '/hrd/applicant-tracking-system/data-personel-request';
    public const URL_DATA_APPLICANTS = '/hrd/applicant-tracking-system/data-applicants';
    public const URL_HR_INTERVIEW = '/hrd/applicant-tracking-system/hr-interview';
    public const URL_USER_INTERVIEW = '/hrd/applicant-tracking-system/user-interview';
    public const URL_FINAL_DECISION = '/hrd/applicant-tracking-system/final-decision';
    public const URL_HIRED_CANDIDATES = '/hrd/applicant-tracking-system/hired-candidates';
    public const URL_FINANCE_OFFERING = '/finance/offering-sallary';
    public const URL_FINANCE_DECISION = '/finance/finance-decision';

    public function notifyByNamaLengkap(?string $namaLengkap, string $title, string $message, string $url): void
    {
        $namaLengkap = trim((string) $namaLengkap);
        if ($namaLengkap === '') {
            return;
        }

        $this->safeSend(function () use ($namaLengkap, $title, $message, $url) {
            Notification::where('nama_lengkap', $namaLengkap)
                ->title($title)
                ->message($message)
                ->url($url)
                ->send();
        });
    }

    public function notifyByKaryawanIds(array $karyawanIds, string $title, string $message, string $url): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $karyawanIds))));
        if ($ids === []) {
            return;
        }

        $this->safeSend(function () use ($ids, $title, $message, $url) {
            Notification::whereIn('id', $ids)
                ->title($title)
                ->message($message)
                ->url($url)
                ->send();
        });
    }

    public function notifyHrdTeam(string $title, string $message, string $url): void
    {
        $hrdId = trim((string) env('HRD_ID', ''));
        if ($hrdId !== '') {
            $members = GetAtasan::where('id', $hrdId)->get();
            $ids = $members->pluck('id')->filter()->unique()->values()->all();
            if ($ids !== []) {
                $this->notifyByKaryawanIds($ids, $title, $message, $url);
                return;
            }
        }

        $departmentId = (int) env('ATS_HRD_DEPARTMENT_ID', 0);
        if ($departmentId > 0) {
            $this->safeSend(function () use ($departmentId, $title, $message, $url) {
                Notification::where('id_department', $departmentId)
                    ->title($title)
                    ->message($message)
                    ->url($url)
                    ->send();
            });
        }
    }

    public function notifyFinanceTeam(string $title, string $message, string $url): void
    {
        $departmentId = (int) env('ATS_FINANCE_DEPARTMENT_ID', 0);
        if ($departmentId <= 0) {
            return;
        }

        $this->safeSend(function () use ($departmentId, $title, $message, $url) {
            Notification::where('id_department', $departmentId)
                ->title($title)
                ->message($message)
                ->url($url)
                ->send();
        });
    }

    public function notifyPersonnelRequestCreator($personnelRequest, string $title, string $message, string $url): void
    {
        if (!$personnelRequest) {
            return;
        }

        $createdBy = is_object($personnelRequest)
            ? ($personnelRequest->created_by ?? null)
            : ($personnelRequest['created_by'] ?? null);

        $this->notifyByNamaLengkap($createdBy, $title, $message, $url);
    }

    public function personnelRequestSubmitted($personnelRequest): void
    {
        $noRequest = $this->noRequest($personnelRequest);
        $this->notifyHrdTeam(
            'Personnel Request Baru',
            "Personnel request {$noRequest} baru diajukan dan menunggu review HRD.",
            self::URL_DATA_PERSONNEL_REQUEST
        );
    }

    public function personnelRequestApproved($personnelRequest): void
    {
        $noRequest = $this->noRequest($personnelRequest);
        $this->notifyPersonnelRequestCreator(
            $personnelRequest,
            'Personnel Request Disetujui',
            "Personnel request {$noRequest} telah disetujui HRD.",
            self::URL_PERSONNEL_REQUEST
        );
    }

    public function personnelRequestRejected($personnelRequest): void
    {
        $noRequest = $this->noRequest($personnelRequest);
        $this->notifyPersonnelRequestCreator(
            $personnelRequest,
            'Personnel Request Ditolak',
            "Personnel request {$noRequest} ditolak oleh HRD.",
            self::URL_PERSONNEL_REQUEST
        );
    }

    public function personnelRequestPublished($personnelRequest): void
    {
        $noRequest = $this->noRequest($personnelRequest);
        $this->notifyPersonnelRequestCreator(
            $personnelRequest,
            'Personnel Request Dipublish',
            "Personnel request {$noRequest} sudah dipublish ke portal rekrutmen.",
            self::URL_PERSONNEL_REQUEST
        );
    }

    public function newApplicantSubmitted($recruitment, $personnelRequest = null): void
    {
        $candidate = $this->candidateName($recruitment);
        $noRequest = $this->noRequest($personnelRequest);
        $this->notifyHrdTeam(
            'Pelamar Baru',
            "Kandidat {$candidate} melamar pada request {$noRequest}.",
            self::URL_DATA_APPLICANTS
        );
        $this->notifyPersonnelRequestCreator(
            $personnelRequest,
            'Pelamar Baru',
            "Kandidat {$candidate} melamar pada personnel request {$noRequest}.",
            self::URL_PERSONNEL_REQUEST
        );
    }

    public function assessmentCompleted($recruitment): void
    {
        $candidate = $this->candidateName($recruitment);
        $this->notifyHrdTeam(
            'Assessment Selesai',
            "Kandidat {$candidate} menyelesaikan assessment dan siap discreening.",
            self::URL_DATA_APPLICANTS
        );
    }

    public function applicantApprovedForHrdInterview($recruitment, $personnelRequest = null): void
    {
        $candidate = $this->candidateName($recruitment);
        $this->notifyPersonnelRequestCreator(
            $personnelRequest,
            'Kandidat Lolos Screening',
            "Kandidat {$candidate} lolos screening dan dijadwalkan HR Interview.",
            self::URL_PERSONNEL_REQUEST
        );
    }

    public function hrdInterviewRescheduled($recruitment): void
    {
        $candidate = $this->candidateName($recruitment);
        $this->notifyHrdTeam(
            'HR Interview Dijadwalkan Ulang',
            "Jadwal HR Interview kandidat {$candidate} telah diperbarui.",
            self::URL_HR_INTERVIEW
        );
    }

    public function hrdInterviewPassed($recruitment, $personnelRequest = null): void
    {
        $candidate = $this->candidateName($recruitment);
        $noRequest = $this->noRequest($personnelRequest);
        $this->notifyPersonnelRequestCreator(
            $personnelRequest,
            'HR Interview Disetujui',
            "Kandidat {$candidate} ({$noRequest}) lolos HR Interview. Silakan lanjut ke User Interview.",
            self::URL_PERSONNEL_REQUEST
        );
    }

    public function hrdInterviewRejected($recruitment, $personnelRequest = null): void
    {
        $candidate = $this->candidateName($recruitment);
        $this->notifyPersonnelRequestCreator(
            $personnelRequest,
            'Kandidat Ditolak HR Interview',
            "Kandidat {$candidate} ditolak pada tahap HR Interview.",
            self::URL_PERSONNEL_REQUEST
        );
    }

    public function profileCompleted($recruitment, $personnelRequest = null): void
    {
        $candidate = $this->candidateName($recruitment);
        $this->notifyPersonnelRequestCreator(
            $personnelRequest,
            'Profil Kandidat Lengkap',
            "Kandidat {$candidate} sudah melengkapi profil dan siap dijadwalkan User Interview.",
            self::URL_PERSONNEL_REQUEST
        );
        $this->notifyHrdTeam(
            'Profil Kandidat Lengkap',
            "Kandidat {$candidate} sudah melengkapi profil.",
            self::URL_USER_INTERVIEW
        );
    }

    public function userInterviewScheduledByRequester($recruitment, $personnelRequest = null): void
    {
        $candidate = $this->candidateName($recruitment);
        $this->notifyHrdTeam(
            'User Interview Dijadwalkan',
            "User telah menjadwalkan User Interview untuk kandidat {$candidate}. Mohon siapkan link GMeet atau ruangan.",
            self::URL_USER_INTERVIEW
        );
    }

    public function userInterviewSchedulePrepared($recruitment, $personnelRequest = null): void
    {
        $candidate = $this->candidateName($recruitment);
        $this->notifyPersonnelRequestCreator(
            $personnelRequest,
            'Sesi User Interview Siap',
            "HRD sudah menyiapkan link/ruangan User Interview untuk kandidat {$candidate}.",
            self::URL_PERSONNEL_REQUEST
        );
    }

    public function userInterviewApproved($recruitment, $personnelRequest = null): void
    {
        $candidate = $this->candidateName($recruitment);
        $this->notifyHrdTeam(
            'User Interview Direkomendasikan',
            "User merekomendasikan kandidat {$candidate} setelah User Interview.",
            self::URL_FINAL_DECISION
        );
        $this->notifyPersonnelRequestCreator(
            $personnelRequest,
            'Keputusan User Interview',
            "Kandidat {$candidate} direkomendasikan setelah User Interview.",
            self::URL_PERSONNEL_REQUEST
        );
    }

    public function userInterviewRejected($recruitment, $personnelRequest = null): void
    {
        $candidate = $this->candidateName($recruitment);
        $this->notifyHrdTeam(
            'User Interview Ditolak',
            "User menolak kandidat {$candidate} setelah User Interview.",
            self::URL_USER_INTERVIEW
        );
    }

    public function salarySubmittedToFinance($recruitment): void
    {
        $candidate = $this->candidateName($recruitment);
        $this->notifyFinanceTeam(
            'Review Gaji Kandidat',
            "Kandidat {$candidate} menunggu review gaji Finance.",
            self::URL_FINANCE_OFFERING
        );
    }

    public function financeDecisionMade($recruitment, string $decision): void
    {
        $candidate = $this->candidateName($recruitment);
        $approved = strtolower($decision) === 'approve';
        $this->notifyHrdTeam(
            $approved ? 'Finance Menyetujui Gaji' : 'Finance Menolak Gaji',
            $approved
                ? "Finance menyetujui penawaran gaji kandidat {$candidate}."
                : "Finance menolak penawaran gaji kandidat {$candidate}.",
            self::URL_FINAL_DECISION
        );
    }

    public function directorSalaryDecision($recruitment, string $decision): void
    {
        $candidate = $this->candidateName($recruitment);
        $normalized = strtolower($decision);
        $title = 'Keputusan Gaji Direktur';
        if ($normalized === 'approve') {
            $message = "Direktur menyetujui gaji kandidat {$candidate}.";
        } elseif ($normalized === 'negotiate' || $normalized === 'negotiated') {
            $message = "Direktur menegosiasi gaji kandidat {$candidate}. HRD perlu tindak lanjut.";
        } else {
            $message = "Direktur menolak gaji kandidat {$candidate}.";
        }

        $this->notifyHrdTeam($title, $message, self::URL_FINAL_DECISION);
    }

    public function candidateHired($recruitment, $personnelRequest = null): void
    {
        $candidate = $this->candidateName($recruitment);
        $this->notifyPersonnelRequestCreator(
            $personnelRequest,
            'Kandidat Diterima',
            "Kandidat {$candidate} dinyatakan diterima (hired).",
            self::URL_PERSONNEL_REQUEST
        );
        $this->notifyHrdTeam(
            'Kandidat Diterima',
            "Kandidat {$candidate} dinyatakan diterima (hired).",
            self::URL_HIRED_CANDIDATES
        );
    }

    public function personnelRequestFulfilled($personnelRequest): void
    {
        $noRequest = $this->noRequest($personnelRequest);
        $this->notifyPersonnelRequestCreator(
            $personnelRequest,
            'Personnel Request Terpenuhi',
            "Personnel request {$noRequest} ditutup karena kebutuhan karyawan sudah terpenuhi.",
            self::URL_PERSONNEL_REQUEST
        );
    }

    public function employeeMigrated($recruitment): void
    {
        $candidate = $this->candidateName($recruitment);
        $this->notifyHrdTeam(
            'Migrasi Karyawan Selesai',
            "Data kandidat {$candidate} berhasil dimigrasikan ke master karyawan.",
            self::URL_HIRED_CANDIDATES
        );
    }

    private function candidateName($recruitment): string
    {
        if (is_object($recruitment)) {
            return trim((string) ($recruitment->nama_lengkap ?? 'Kandidat'));
        }

        return trim((string) ($recruitment['nama_lengkap'] ?? 'Kandidat'));
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

    private function safeSend(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            Log::warning('ATS in-app notification failed: ' . $exception->getMessage());
        }
    }
}
