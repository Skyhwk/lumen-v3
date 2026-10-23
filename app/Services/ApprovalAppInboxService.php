<?php

namespace App\Services;

use App\Models\ApprovalAppDevice;
use App\Models\MasterKaryawan;
use App\Models\NewRecruitment;
use App\Models\SalaryAdjustmentApprovalToken;
use App\Models\SalaryAdjustmentRequest;
use Illuminate\Support\Facades\Schema;

class ApprovalAppInboxService
{
    public function registerDevice(string $email, string $deviceId, ?string $fcmToken, string $platform): void
    {
        $email = strtolower(trim($email));
        $deviceId = trim($deviceId);
        if ($email === '' || $deviceId === '') {
            throw new \InvalidArgumentException('Email dan device_id wajib diisi.');
        }

        ApprovalAppDevice::updateOrCreate(
            ['email' => $email, 'device_id' => $deviceId],
            [
                'fcm_token' => $fcmToken ? trim($fcmToken) : null,
                'platform' => $platform !== '' ? $platform : 'android',
            ]
        );
    }

    public function counts(): array
    {
        return [
            'kandidat' => [
                'pending' => $this->countKandidatBucket('menunggu'),
                'keep' => $this->countKandidatBucket('keep'),
            ],
            'penyesuaian' => [
                'pending' => $this->countPenyesuaianBucket('menunggu'),
            ],
        ];
    }

    public function inbox(string $domain, string $bucket, int $page, int $perPage): array
    {
        $domain = strtolower(trim($domain));
        $bucket = strtolower(trim($bucket));
        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));

        if ($domain === 'kandidat') {
            return $this->inboxKandidat($bucket, $page, $perPage);
        }

        if ($domain === 'penyesuaian') {
            return $this->inboxPenyesuaian($bucket, $page, $perPage);
        }

        throw new \InvalidArgumentException('Domain tidak valid.');
    }

    public function findKandidat(int $id): ?array
    {
        return app(ApprovalAppCandidateDetailService::class)->find($id);
    }

    private function inboxKandidat(string $bucket, int $page, int $perPage): array
    {
        $this->assertKandidatBucket($bucket);

        $rows = $this->baseKandidatQuery($bucket)
            ->orderByDesc('new_recruitment.id')
            ->get()
            ->filter(fn ($row) => $this->matchesKandidatBucket($row, $bucket))
            ->values();

        $total = $rows->count();
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'data' => $slice->map(fn ($row) => $this->mapKandidatRow($row, false))->all(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }

    private function countKandidatBucket(string $bucket): int
    {
        $this->assertKandidatBucket($bucket);

        return $this->baseKandidatQuery($bucket)
            ->get()
            ->filter(fn ($row) => $this->matchesKandidatBucket($row, $bucket))
            ->count();
    }

    private function assertKandidatBucket(string $bucket): void
    {
        if (!in_array($bucket, ['menunggu', 'keep', 'setuju', 'tolak'], true)) {
            throw new \InvalidArgumentException('Bucket kandidat tidak valid.');
        }
    }

    private function matchesKandidatBucket($row, string $bucket): bool
    {
        switch ($bucket) {
            case 'menunggu':
                return RecruitmentStatusService::isAwaitingIbuDirekturApproval($row);
            case 'keep':
                return RecruitmentStatusService::isKeptCandidate($row);
            case 'setuju':
                return RecruitmentStatusService::hasManagementDecisionApproved($row);
            case 'tolak':
                return $this->hasManagementDecisionRejected($row);
            default:
                return false;
        }
    }

    private function hasManagementDecisionRejected($recruitment): bool
    {
        foreach (RecruitmentStatusService::parseMetaHistory($recruitment) as $entry) {
            if (strtolower((string) ($entry['status'] ?? '')) === 'management_decision_rejected') {
                return true;
            }
        }

        if (Schema::hasColumn('new_recruitment', 'rejected_decision')) {
            $flag = (int) (is_object($recruitment)
                ? ($recruitment->rejected_decision ?? 0)
                : ($recruitment['rejected_decision'] ?? 0));

            return $flag === 1
                && strtolower((string) (is_object($recruitment) ? ($recruitment->status ?? '') : ($recruitment['status'] ?? ''))) === 'management_decision';
        }

        return false;
    }

    private function baseKandidatQuery(?string $bucket = null)
    {
        $query = NewRecruitment::query()
            ->select('new_recruitment.*')
            ->whereNotNull('personnel_request_id')
            ->where('personnel_request_id', '!=', '');

        if ($bucket === 'tolak') {
            AtsFinalDecisionCandidateScope::applyRejected($query);
        } else {
            AtsFinalDecisionCandidateScope::applyActive($query);
        }

        return $query;
    }

    private function mapKandidatRow($row, bool $detail): array
    {
        $pr = $row->personnelRequest ?? $row->personalRequest;
        $subtitle = $pr->divisi_alias ?? $row->posisi_dilamar ?? '-';

        $payload = [
            'id' => (string) $row->id,
            'domain' => 'kandidat',
            'title' => $row->nama_lengkap ?? '-',
            'subtitle' => $subtitle,
            'created_at' => optional($row->created_at)->toDateTimeString(),
            'token_approval' => $row->token_approval,
        ];
        $payload = array_merge($payload, $this->selfieFieldsForRecruitment($row));

        if ($detail) {
            $payload['status'] = $row->status;
            $payload['can_decide'] = RecruitmentStatusService::isAwaitingIbuDirekturApproval($row)
                || RecruitmentStatusService::isKeptCandidate($row);
        }

        return $payload;
    }

    private function selfieFieldsForRecruitment($row): array
    {
        $picture = is_object($row) ? ($row->picture ?? null) : ($row['picture'] ?? null);
        if (!$picture || trim((string) $picture) === '') {
            return ['selfie_url' => null, 'selfie_path' => null];
        }

        /** @var RecruitmentPictureService $pictureService */
        $pictureService = app(RecruitmentPictureService::class);
        $url = $pictureService->toPathUrl($picture);
        $base = basename((string) $picture);
        if (str_starts_with((string) $picture, 'data:image') || filter_var($picture, FILTER_VALIDATE_URL)) {
            return [
                'selfie_url' => $url ?: (string) $picture,
                'selfie_path' => null,
            ];
        }

        $relativePath = 'recruitment/' . $base;
        $pathOnDisk = public_path('recruitment' . DIRECTORY_SEPARATOR . $base);
        if (!is_file($pathOnDisk)) {
            $relativePath = 'recruitment/foto/' . $base;
        }

        return [
            'selfie_url' => $url,
            'selfie_path' => $relativePath,
        ];
    }

    public function employeePhotoFields(?MasterKaryawan $employee): array
    {
        if (!$employee) {
            return ['selfie_url' => null, 'selfie_path' => null];
        }

        $image = trim((string) ($employee->image ?? ''));
        $ignored = ['-', 'null', '', 'no_image.jpg', 'no_image_2.jpg'];
        if ($image === '' || in_array(strtolower($image), $ignored, true)) {
            return ['selfie_url' => null, 'selfie_path' => null];
        }

        if (str_starts_with($image, 'data:image') || filter_var($image, FILTER_VALIDATE_URL)) {
            return ['selfie_url' => $image, 'selfie_path' => null];
        }

        $normalized = str_replace('\\', '/', $image);
        if (str_contains($normalized, 'Foto_Karyawan/')) {
            $relativePath = ltrim(preg_replace('#^.*?Foto_Karyawan/#i', 'Foto_Karyawan/', $normalized) ?? $normalized, '/');
        } else {
            $filename = basename($normalized);
            if ($filename === '') {
                return ['selfie_url' => null, 'selfie_path' => null];
            }
            $relativePath = 'Foto_Karyawan/' . $filename;
        }

        $baseUrl = rtrim((string) env('APP_URL_PATH', ''), '/');
        if ($baseUrl === '') {
            $baseUrl = rtrim((string) env('APP_URL', ''), '/');
            if ($baseUrl !== '' && !preg_match('#/public$#i', $baseUrl)) {
                $baseUrl .= '/public';
            }
        }

        $url = $baseUrl !== '' ? $baseUrl . '/' . $relativePath : null;

        return [
            'selfie_url' => $url,
            'selfie_path' => $relativePath,
        ];
    }

    private function inboxPenyesuaian(string $bucket, int $page, int $perPage): array
    {
        if (!in_array($bucket, ['menunggu', 'setuju', 'tolak'], true)) {
            throw new \InvalidArgumentException('Bucket penyesuaian tidak valid.');
        }

        $query = SalaryAdjustmentApprovalToken::query()
            ->where('approver_role', SalaryAdjustmentEmailService::ROLE_IBU)
            ->orderByDesc('id');

        if ($bucket === 'menunggu') {
            $query->where('is_active', true)
                ->whereNull('decision')
                ->whereHas('request', function ($q) {
                    $q->where('is_active', true)
                        ->where('status', SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_IBU);
                });
        } elseif ($bucket === 'setuju') {
            $query->where('decision', 'approve');
        } else {
            $query->where('decision', 'reject');
        }

        $total = (clone $query)->count();
        $tokens = $query->forPage($page, $perPage)->get();

        return [
            'data' => $tokens->map(fn ($token) => $this->mapPenyesuaianToken($token))->all(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }

    private function countPenyesuaianBucket(string $bucket): int
    {
        if ($bucket !== 'menunggu') {
            return 0;
        }

        return SalaryAdjustmentApprovalToken::query()
            ->where('approver_role', SalaryAdjustmentEmailService::ROLE_IBU)
            ->where('is_active', true)
            ->whereNull('decision')
            ->whereHas('request', function ($q) {
                $q->where('is_active', true)
                    ->where('status', SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_IBU);
            })
            ->count();
    }

    private function mapPenyesuaianToken(SalaryAdjustmentApprovalToken $token): array
    {
        /** @var SalaryAdjustmentRequest|null $record */
        $record = $token->request;
        $employee = $record ? MasterKaryawan::find($record->employee_id) : null;

        return array_merge([
            'id' => (string) ($record->id ?? $token->request_id),
            'domain' => 'penyesuaian',
            'title' => $employee->nama_lengkap ?? ($record->no_document ?? 'Penyesuaian gaji'),
            'subtitle' => $record->jabatan ?? '-',
            'created_at' => optional($record->created_at ?? $token->created_at)->toDateTimeString(),
            'token' => $token->token,
            'decided_at' => optional($token->decided_at)->toDateTimeString(),
        ], $this->employeePhotoFields($employee));
    }
}
