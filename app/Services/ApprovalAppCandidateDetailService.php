<?php

namespace App\Services;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\api\Concerns\BuildsCandidateAssessmentPreview;
use App\Models\NewRecruitment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApprovalAppCandidateDetailService
{
    use BuildsCandidateAssessmentPreview;

    public function find(int $id): ?array
    {
        $candidate = NewRecruitment::with([
            'personalRequest.masterJabatan',
            'personalRequest.masterDivisi',
            'appliedPositionJabatan',
            'masterJabatan',
            'hrdInterview',
            'userInterview',
            'candidateDataOffer',
        ])->find($id);

        if (!$candidate || !$candidate->personnel_request_id) {
            return null;
        }

        $pictureService = app(RecruitmentPictureService::class);
        $personnelRequest = $candidate->personalRequest;
        $birthDate = $candidate->tanggal_lahir ?? $candidate->tempat_tanggal_lahir ?? null;
        $shioElemen = ShioElemenHelper::resolve($birthDate, $candidate->shio ?? null, $candidate->elemen ?? null);

        $salaryOffer = DB::table('sallary_offer')
            ->where('new_recruitment_id', $candidate->id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (!$salaryOffer) {
            $salaryOffer = DB::table('sallary_offer')
                ->where('new_recruitment_id', $candidate->id)
                ->orderByDesc('id')
                ->first();
        }

        $cdo = $candidate->candidateDataOffer;
        $salaryDisplay = $this->resolveHrdSalaryDisplay($cdo, $salaryOffer ?: null, $candidate);
        $profileSalary = $this->resolveCandidateProfileSalaries($candidate);
        $selfieFields = $this->selfieFields($candidate, $pictureService);

        $candidatePayload = array_merge(
            $this->formatCandidatePreviewItem($candidate, $pictureService),
            [
                'usia' => $this->formatUsia($candidate),
                'shio' => $shioElemen['shio'] ?? $candidate->shio ?? null,
                'elemen' => $shioElemen['elemen'] ?? $candidate->elemen ?? null,
                'tanggal_join_tercepat' => $candidate->tanggal_join_tercepat ?? null,
                'gaji_terakhir' => $profileSalary['gaji_terakhir'],
                'ekspetasi_gaji' => $profileSalary['ekspetasi_gaji'],
                'no_whatsapp' => $candidate->no_whatsapp ?? null,
            ],
            $selfieFields
        );

        return [
            'id' => (string) $candidate->id,
            'domain' => 'kandidat',
            'title' => $candidate->nama_lengkap ?? '-',
            'subtitle' => optional($personnelRequest)->divisi_alias ?? $candidate->posisi_dilamar ?? '-',
            'token_approval' => $candidate->token_approval,
            'status' => $candidate->status,
            'can_decide' => RecruitmentStatusService::isAwaitingIbuDirekturApproval($candidate)
                || RecruitmentStatusService::isKeptCandidate($candidate),
            'candidate' => $candidatePayload,
            'request' => $personnelRequest ? [
                'id' => $personnelRequest->id,
                'no_request' => $personnelRequest->no_request,
                'posisi' => optional($personnelRequest->masterJabatan)->nama_jabatan ?: $personnelRequest->posisi,
                'divisi' => optional($personnelRequest->masterDivisi)->nama_divisi
                    ?: ($personnelRequest->divisi_alias ?: $personnelRequest->divisi),
                'minimum_matching' => $personnelRequest->minimum_matching,
            ] : null,
            'salary_offer' => $salaryOffer ? [
                'sallary_offer_hrd' => $salaryOffer->sallary_offer_hrd ?? null,
                'final_sallary' => $salaryOffer->final_sallary ?? null,
                'salary_offer_candidate' => $salaryOffer->salary_offer_candidate ?? null,
                'tunjangan_offer_candidate' => $salaryOffer->tunjangan_offer_candidate ?? null,
            ] : null,
            'candidate_data_offer' => $cdo ? [
                'gaji_pokok' => $cdo->gaji_pokok ?? null,
                'tunjangan_kerja' => $cdo->tunjangan_kerja ?? null,
                'tanggal_mulai_kerja' => $cdo->tanggal_mulai_kerja ?? null,
            ] : null,
            'salary_display' => $salaryDisplay,
            'profile_salary' => $profileSalary,
        ];
    }

    /**
     * Gaji profil kandidat — selaras ATS Final Decision (ModalAddExpectedSalary / ModalDetailFinalDecision).
     * Kolom expected_salary di tabel ATS = penawaran HRD (bukan field ini).
     */
    private function resolveCandidateProfileSalaries(NewRecruitment $candidate): array
    {
        $gajiTerakhir = $this->positiveAmount($candidate->gaji_terakhir ?? null);
        $ekspektasiLamaran = $this->positiveAmount($candidate->ekspetasi_gaji ?? null);

        $expectedSalaryAts = $ekspektasiLamaran > 0
            ? $ekspektasiLamaran
            : ($gajiTerakhir > 0 ? $gajiTerakhir : null);

        return [
            'gaji_terakhir' => $gajiTerakhir > 0 ? $gajiTerakhir : null,
            'ekspetasi_gaji' => $ekspektasiLamaran > 0 ? $ekspektasiLamaran : null,
            'expected_salary_ats' => $expectedSalaryAts,
        ];
    }

    /**
     * Rincian gaji untuk tampilan — selaras GenerateMessageAtsEmail / Final Decision.
     */
    private function resolveHrdSalaryDisplay($cdo, $salaryOffer, NewRecruitment $candidate): array
    {
        $pokokCdo = $this->positiveAmount($cdo ? ($cdo->gaji_pokok ?? null) : null);
        $tunjanganCdo = $this->positiveAmount($cdo ? ($cdo->tunjangan_kerja ?? null) : null);

        if ($pokokCdo > 0 || $tunjanganCdo > 0) {
            $total = $pokokCdo + $tunjanganCdo;

            return [
                'gaji_pokok' => $pokokCdo,
                'tunjangan_kerja' => $tunjanganCdo,
                'total' => $total > 0 ? $total : null,
                'has_detail_breakdown' => true,
                'hint' => null,
            ];
        }

        $fallbackTotal = $this->firstPositiveAmount([
            $salaryOffer ? ($salaryOffer->final_sallary ?? null) : null,
            $salaryOffer ? ($salaryOffer->sallary_offer_hrd ?? null) : null,
            $candidate->ekspetasi_gaji ?? null,
        ]);

        if ($fallbackTotal <= 0) {
            return [
                'gaji_pokok' => null,
                'tunjangan_kerja' => null,
                'total' => null,
                'has_detail_breakdown' => false,
                'hint' => 'Belum ada penawaran gaji HRD yang tercatat.',
            ];
        }

        return [
            'gaji_pokok' => $fallbackTotal,
            'tunjangan_kerja' => 0,
            'total' => $fallbackTotal,
            'has_detail_breakdown' => false,
            'hint' => 'HRD hanya mencatat total penawaran; tunjangan belum dirinci terpisah.',
        ];
    }

    private function positiveAmount($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            $n = (float) $value;

            return $n > 0 ? $n : 0.0;
        }

        $clean = preg_replace('/[^\d]/', '', (string) $value);
        if ($clean === '') {
            return 0.0;
        }

        $n = (float) $clean;

        return $n > 0 ? $n : 0.0;
    }

    private function firstPositiveAmount(array $candidates): float
    {
        foreach ($candidates as $value) {
            $n = $this->positiveAmount($value);
            if ($n > 0) {
                return $n;
            }
        }

        return 0.0;
    }

    private function formatUsia(NewRecruitment $candidate): ?string
    {
        $year = $this->extractBirthYear($candidate);
        if (!$year) {
            return null;
        }

        $age = Carbon::now()->year - $year;

        return $age . ' th';
    }

    private function extractBirthYear(NewRecruitment $candidate): ?int
    {
        $raw = $candidate->tanggal_lahir ?? $candidate->tempat_tanggal_lahir ?? null;
        if (!$raw) {
            return null;
        }

        if (preg_match('/(\d{4})/', (string) $raw, $m)) {
            $y = (int) $m[1];
            if ($y >= 1940 && $y <= (int) date('Y')) {
                return $y;
            }
        }

        return null;
    }

    private function selfieFields(NewRecruitment $candidate, RecruitmentPictureService $pictureService): array
    {
        $picture = $candidate->picture ?? null;
        if (!$picture || trim((string) $picture) === '') {
            return ['selfie_url' => null, 'selfie_path' => null];
        }

        $url = $pictureService->toPathUrl($picture);
        $base = basename((string) $picture);
        $relativePath = 'recruitment/' . $base;
        $pathOnDisk = public_path('recruitment' . DIRECTORY_SEPARATOR . $base);
        if (!is_file($pathOnDisk)) {
            $relativePath = 'recruitment/foto/' . $base;
        }

        return [
            'selfie_url' => $url,
            'selfie_path' => $relativePath,
            'picture_url' => $pictureService->toDataUri($picture),
        ];
    }
}
