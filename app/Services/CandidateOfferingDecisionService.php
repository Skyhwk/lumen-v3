<?php

namespace App\Services;

use App\Models\CandidateDataOffers;
use App\Models\NewRecruitment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CandidateOfferingDecisionService
{
    public const DEADLINE_DAYS = 2;
    public const DEADLINE_HOUR = 23;
    public const DEADLINE_TIMEZONE = 'Asia/Jakarta';
    public const HISTORY_SENT = 'candidate_offering_sent';
    public const HISTORY_APPROVED = 'candidate_offering_approved';
    public const HISTORY_REJECTED = 'candidate_offering_rejected';
    public const HISTORY_NEGOTIATED = 'candidate_offering_negotiated';

    public static function parseHistory($recruitment): array
    {
        $raw = is_object($recruitment) ? ($recruitment->meta_history ?? '[]') : '[]';
        $history = json_decode($raw ?: '[]', true);

        return is_array($history) ? $history : [];
    }

    public static function lastHistoryStatus($recruitment): string
    {
        $history = self::parseHistory($recruitment);
        if ($history === []) {
            return '';
        }

        $last = $history[count($history) - 1];

        return strtolower(trim((string) ($last['status'] ?? '')));
    }

    public static function hasNegotiatedOnce($recruitment): bool
    {
        foreach (self::parseHistory($recruitment) as $entry) {
            if (($entry['status'] ?? '') === self::HISTORY_NEGOTIATED) {
                return true;
            }
        }

        return false;
    }

    public static function isWaitingCandidateOffering($recruitment): bool
    {
        $status = (string) ($recruitment->status ?? '');
        $lastHistoryStatus = self::lastHistoryStatus($recruitment);

        return $lastHistoryStatus === self::HISTORY_SENT
            && in_array($status, ['salary_offer', 'internal_sallary_offer'], true);
    }

    public static function lastOfferingSentAt($recruitment): ?Carbon
    {
        $history = self::parseHistory($recruitment);
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['status'] ?? '') !== self::HISTORY_SENT) {
                continue;
            }

            $at = trim((string) ($history[$i]['at'] ?? ''));
            if ($at === '') {
                return null;
            }

            try {
                return Carbon::parse($at);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /** Tanggal kirim + 2 hari, jam 23:00 WIB. Senin 10:00 → Rabu 23:00. */
    public static function deadlineFrom(?Carbon $from = null): Carbon
    {
        return ($from ?: Carbon::now())
            ->copy()
            ->timezone(self::DEADLINE_TIMEZONE)
            ->startOfDay()
            ->addDays(self::DEADLINE_DAYS)
            ->setTime(self::DEADLINE_HOUR, 0, 0);
    }

    public static function deadlineAt($recruitment): ?Carbon
    {
        $sentAt = self::lastOfferingSentAt($recruitment);

        return $sentAt ? self::deadlineFrom($sentAt) : null;
    }

    public static function isDeadlinePassed($recruitment, ?Carbon $now = null): bool
    {
        $deadline = self::deadlineAt($recruitment);
        if (!$deadline) {
            return false;
        }

        $now = ($now ?: Carbon::now())->copy()->timezone(self::DEADLINE_TIMEZONE);

        return $now->greaterThanOrEqualTo($deadline);
    }

    public static function invalidState($recruitment): ?array
    {
        $lastHistoryStatus = self::lastHistoryStatus($recruitment);

        if ($lastHistoryStatus !== '' && strpos($lastHistoryStatus, 'reject') !== false) {
            return [
                'result' => 'expired',
                'message' => 'Link offering letter sudah kedaluwarsa karena proses kandidat terakhir berstatus ditolak.',
            ];
        }

        if ($lastHistoryStatus === self::HISTORY_NEGOTIATED) {
            return [
                'result' => 'unavailable',
                'message' => 'Negosiasi Anda sudah tercatat. Tim HRD akan meninjau usulan Anda.',
            ];
        }

        if ($lastHistoryStatus === self::HISTORY_APPROVED) {
            return [
                'result' => 'unavailable',
                'message' => 'Offering letter ini sudah disetujui sebelumnya.',
            ];
        }

        if (!self::isWaitingCandidateOffering($recruitment)) {
            return [
                'result' => 'unavailable',
                'message' => 'Link offering letter sudah tidak valid untuk tahap proses kandidat saat ini.',
            ];
        }

        return null;
    }

    /**
     * Auto-approve jika lewat H+2 jam 23:00 WIB. Return payload approved, atau null jika masih dalam tenggat.
     */
    public static function approveIfExpired($recruitment, ?Carbon $now = null): ?array
    {
        $now = $now ?: Carbon::now();
        if (!self::isWaitingCandidateOffering($recruitment) || !self::isDeadlinePassed($recruitment, $now)) {
            return null;
        }

        return self::approve($recruitment, $now, 'System', [
            'auto_approved' => true,
            'reason' => 'Tidak ada respon kandidat sampai H+2 pukul 23:00 WIB.',
        ]);
    }

    public static function approve($recruitment, Carbon $now, string $decidedBy = 'Candidate', array $extra = []): array
    {
        (new RecruitmentStatusService())->update(
            $recruitment->id,
            'internal_sallary_offer',
            $now,
            self::HISTORY_APPROVED,
            array_filter(array_merge([
                'decided_by' => $decidedBy,
            ], $extra), function ($value) {
                return $value !== null;
            })
        );

        self::notifyDirector($recruitment);

        $fresh = DB::table('new_recruitment')->where('id', $recruitment->id)->first();

        return [
            'result' => 'approved',
            'auto_approved' => !empty($extra['auto_approved']),
            'message' => !empty($extra['auto_approved'])
                ? 'Batas waktu penawaran (H+2 pukul 23:00 WIB) telah terlewati. Penawaran gaji otomatis dinyatakan disetujui.'
                : 'Offering letter berhasil Anda setujui.',
            'decided_at' => $now->toDateTimeString(),
            'candidate' => self::candidatePayload($fresh ?: $recruitment),
        ];
    }

    public static function reject($recruitment, Carbon $now, string $rejectReason): array
    {
        (new RecruitmentStatusService())->update(
            $recruitment->id,
            'salary_offer',
            $now,
            self::HISTORY_REJECTED,
            [
                'decided_by' => 'Candidate',
                'reject_reason' => $rejectReason,
            ]
        );

        $fresh = DB::table('new_recruitment')->where('id', $recruitment->id)->first();

        return [
            'result' => 'rejected',
            'message' => 'Offering letter berhasil Anda tolak.',
            'decided_at' => $now->toDateTimeString(),
            'candidate' => self::candidatePayload($fresh ?: $recruitment),
        ];
    }

    public static function negotiate(
        $recruitment,
        Carbon $now,
        float $salary,
        float $tunjangan,
        string $startDate
    ): array {
        $startDateTime = self::normalizeStartDate($startDate);
        if ($startDateTime === null) {
            throw new \InvalidArgumentException('Tanggal mulai kerja tidak valid.');
        }

        SallaryOfferService::upsertActive(
            (int) $recruitment->id,
            [
                'salary_offer_candidate' => $salary,
                'tunjangan_offer_candidate' => $tunjangan,
            ],
            'Candidate'
        );

        self::updateStartDateWithHistory((int) $recruitment->id, $startDateTime, 'Candidate');

        (new RecruitmentStatusService())->update(
            $recruitment->id,
            'salary_offer',
            $now,
            self::HISTORY_NEGOTIATED,
            [
                'decided_by' => 'Candidate',
                'salary_offer_candidate' => $salary,
                'tunjangan_offer_candidate' => $tunjangan,
                'tanggal_mulai_kerja' => $startDateTime,
            ]
        );

        $fresh = DB::table('new_recruitment')->where('id', $recruitment->id)->first();

        return [
            'result' => 'negotiated',
            'message' => 'Usulan negosiasi berhasil dikirim. Tim HRD akan meninjau gaji, tunjangan, dan tanggal mulai kerja yang Anda ajukan.',
            'decided_at' => $now->toDateTimeString(),
            'candidate' => self::candidatePayload($fresh ?: $recruitment),
            'negotiated' => [
                'salary_offer_candidate' => $salary,
                'tunjangan_offer_candidate' => $tunjangan,
                'tanggal_mulai_kerja' => $startDateTime,
            ],
        ];
    }

    public static function currentOffer($recruitment): array
    {
        $offer = SallaryOfferService::getActive((int) $recruitment->id);
        $cdo = CandidateDataOffers::where('new_recruitment_id', $recruitment->id)->first();
        $startRaw = optional($cdo)->tanggal_mulai_kerja;
        $startDate = null;
        if (!empty($startRaw)) {
            try {
                $startDate = Carbon::parse($startRaw)->toDateString();
            } catch (\Throwable $e) {
                $startDate = substr((string) $startRaw, 0, 10);
            }
        }

        return [
            'gaji_pokok' => optional($cdo)->gaji_pokok
                ?? optional($offer)->sallary_offer_hrd
                ?? ($recruitment->ekspetasi_gaji ?? null),
            'tunjangan_kerja' => optional($cdo)->tunjangan_kerja ?? 0,
            'tanggal_mulai_kerja' => $startDate,
            'salary_offer_candidate' => optional($offer)->salary_offer_candidate,
            'tunjangan_offer_candidate' => optional($offer)->tunjangan_offer_candidate,
            'offering_sent_at' => optional(self::lastOfferingSentAt($recruitment))->toDateTimeString(),
            'offering_expires_at' => optional(self::deadlineAt($recruitment))->toDateTimeString(),
        ];
    }

    public static function candidatePayload($recruitment): array
    {
        $birthDate = $recruitment->tanggal_lahir ?? $recruitment->tempat_tanggal_lahir ?? null;
        $shioElemen = \App\Helpers\ShioElemenHelper::resolve(
            $birthDate,
            $recruitment->shio ?? null,
            $recruitment->elemen ?? null
        );

        return [
            'nama_lengkap' => $recruitment->nama_lengkap,
            'posisi_dilamar' => self::positionLabel($recruitment),
            'shio' => $shioElemen['shio'] ?? $recruitment->shio ?? '-',
            'elemen' => $shioElemen['elemen'] ?? $recruitment->elemen ?? '-',
            'gaji_terakhir' => $recruitment->gaji_terakhir,
            'ekspetasi_gaji' => $recruitment->ekspetasi_gaji,
            'picture_base64' => app(RecruitmentPictureService::class)->toDataUri($recruitment->picture ?? null),
        ];
    }

    public static function parseMoney($value): ?float
    {
        $parsed = RequesterSalaryApprovalService::parseRupiahToInt($value);

        return $parsed !== null ? (float) $parsed : null;
    }

    private static function normalizeStartDate(string $startDate): ?string
    {
        $startDate = trim($startDate);
        if ($startDate === '') {
            return null;
        }

        try {
            return Carbon::parse($startDate)->startOfDay()->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function updateStartDateWithHistory(int $recruitmentId, string $newStartDate, string $by): void
    {
        $cdo = CandidateDataOffers::where('new_recruitment_id', $recruitmentId)->first();
        $previous = optional($cdo)->tanggal_mulai_kerja;
        $payload = [
            'tanggal_mulai_kerja' => $newStartDate,
            'updated_by' => $by,
        ];

        if (!$cdo) {
            $payload['created_by'] = $by;
        }

        CandidateDataOffers::updateOrCreate(
            ['new_recruitment_id' => $recruitmentId],
            $payload
        );

        CandidateDataOffers::appendTanggalMulaiKerjaHistory(
            $recruitmentId,
            $previous,
            $newStartDate,
            self::HISTORY_NEGOTIATED,
            $by
        );
    }

    private static function notifyDirector($recruitment): void
    {
        $targetEmail = trim((string) env('EMAIL_DIREKTUR_BAPAK', ''));
        if ($targetEmail === '') {
            Log::warning('Director salary offer email skipped: EMAIL_DIREKTUR_BAPAK empty', [
                'recruitment_id' => $recruitment->id ?? null,
            ]);
            return;
        }

        $applicant = NewRecruitment::with([
            'personnelRequest.masterJabatan',
            'masterJabatan',
            'sallaryOffer',
            'hrdInterview',
            'userInterview',
            'candidateProfile',
            'candidateEducations',
            'candidateWorkExperiences',
            'candidateMedicalInformation',
        ])->find($recruitment->id);

        if (!$applicant) {
            return;
        }

        if (empty($applicant->token_approval)) {
            $tokenService = new GenerateToken();
            $tokenKey = $applicant->id . ($applicant->nama_lengkap ?? '') . 'salary_approval' . str_replace('.', '', microtime(true));
            $token = $tokenService->encryptv1(md5($tokenKey) . '|' . date('Y-m-d'));
            $applicant->token_approval = $token;
            $applicant->save();
        } else {
            $token = $applicant->token_approval;
        }

        $btn = GenerateMessageAtsEmail::buildSalaryDecisionButtons($applicant, $token);
        $assessmentService = app(GenerateAssessmentDocumentService::class);
        $documents = [];

        try {
            $bodyEmail = GenerateMessageAtsEmail::bodyEmailSallaryOffer($applicant, $btn);
            $attachmentBundle = $assessmentService->prepareDirectorEmailAttachments((int) $applicant->id);
            $documents = $attachmentBundle['documents'] ?? [];
            $attachments = $attachmentBundle['attachments'] ?? [];

            $emailQuery = SendEmail::where('to', $targetEmail)
                ->where('subject', 'Permohonan Persetujuan Offering Salary - ' . ($applicant->nama_lengkap ?? 'Kandidat'))
                ->where('body', $bodyEmail)
                ->where('karyawan', 'HRD')
                ->noReply();

            if (!empty($attachments)) {
                $emailQuery->where('attachment', $attachments);
            }

            $emailQuery->send();

            SallaryOfferService::upsertActive(
                (int) $applicant->id,
                ['email_sent_at' => Carbon::now()],
                'HRD'
            );
        } catch (\Throwable $exception) {
            Log::warning('Director salary offer email failed after candidate approve', [
                'recruitment_id' => $applicant->id,
                'message' => $exception->getMessage(),
            ]);
        } finally {
            $assessmentService->cleanupDocuments($documents);
        }
    }

    private static function positionLabel($recruitment): string
    {
        return GenerateMessageAtsEmail::posisiLabelCandidate($recruitment);
    }
}
