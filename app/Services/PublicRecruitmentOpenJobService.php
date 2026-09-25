<?php

namespace App\Services;

use App\Models\PersonnelRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PublicRecruitmentOpenJobService
{
    public function getOpenJobs(): Collection
    {
        $data = $this->baseQuery()
            ->select($this->listSelectColumns())
            ->get();

        $data = app(PublicRecruitmentJobListService::class)
            ->filterDuplicatePositions($data);

        return $this->enrichJobs($data);
    }

    public function getOpenJob(string $noRequest): ?object
    {
        $job = $this->baseQuery()
            ->where('no_request', $noRequest)
            ->select($this->detailSelectColumns())
            ->first();

        if (!$job) {
            return null;
        }

        return $this->enrichJobs(collect([$job]))->first();
    }

    private function baseQuery()
    {
        $query = PersonnelRequest::query()
            ->leftJoin('master_divisi as md', 'md.id', '=', 'personnel_requests.divisi')
            ->leftJoin('master_cabang as mc', 'mc.id', '=', 'personnel_requests.lokasi_penempatan_cabang');

        if (Schema::hasColumn('personnel_requests', 'is_active')) {
            $query->where('personnel_requests.is_active', true);
        }

        if (Schema::hasColumn('personnel_requests', 'is_reject')) {
            $query->where('personnel_requests.is_reject', false);
        }

        if (Schema::hasColumn('personnel_requests', 'is_publish')) {
            $query->where('personnel_requests.is_publish', true);
        }

        return $query;
    }

    private function listSelectColumns(): array
    {
        $columns = [
            'personnel_requests.id',
            'personnel_requests.created_at',
            'no_request',
            'divisi',
            'jumlah_personal',
            'lokasi_penempatan_cabang',
            'pengalaman_kerja',
            'pendidikan',
            'gender',
            'prioritas',
            'divisi_alias',
            'grade_master_karyawan',
            'personnel_requests.created_by',
            'md.nama_divisi as divisi_name',
            'mc.nama_cabang as placement',
        ];

        if (Schema::hasColumn('personnel_requests', 'requirement')) {
            $columns[] = 'requirement';
        }

        if (Schema::hasColumn('personnel_requests', 'gambar')) {
            $columns[] = 'personnel_requests.gambar';
        }

        if (Schema::hasColumn('personnel_requests', 'use_user_assessment')) {
            $columns[] = 'use_user_assessment';
        }

        return $columns;
    }

    private function detailSelectColumns(): array
    {
        return array_values(array_diff($this->listSelectColumns(), [
            'personnel_requests.id',
            'personnel_requests.created_at',
        ]));
    }

    private function enrichJobs(Collection $jobs): Collection
    {
        $imageService = app(PersonnelRequestImageService::class);
        $assessmentService = app(UserAssessmentCategoryService::class);

        return $jobs->map(function ($job) use ($imageService, $assessmentService) {
            try {
                $imageService->appendToJob($job);
            } catch (\Throwable $exception) {
                $job->image_url = null;
                Log::warning('public_recruitment.image_enrich_failed', [
                    'no_request' => $job->no_request ?? null,
                    'message' => $exception->getMessage(),
                ]);
            }

            try {
                if (Schema::hasTable('question_categories')) {
                    $assessmentService->appendLegacyConfigFields($job);
                }
            } catch (\Throwable $exception) {
                Log::warning('public_recruitment.assessment_enrich_failed', [
                    'no_request' => $job->no_request ?? null,
                    'message' => $exception->getMessage(),
                ]);
            }

            if ($job instanceof PersonnelRequest) {
                $job->makeHidden([
                    'id',
                    'created_at',
                    'divisi',
                    'lokasi_penempatan_cabang',
                    'created_by',
                ]);
            }

            return $job;
        })->values();
    }
}
