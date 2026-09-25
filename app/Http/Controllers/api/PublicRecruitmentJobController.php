<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\PublicRecruitmentOpenJobService;
use App\Support\PublicRecruitmentJobPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PublicRecruitmentJobController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $rawJobs = app(PublicRecruitmentOpenJobService::class)->getOpenJobs();
            $data = $rawJobs->map(fn ($job) => PublicRecruitmentJobPresenter::bundle($job));

            return $this->jsonResponse([
                'status' => true,
                'message' => 'Daftar lowongan beserta detail berhasil diambil.',
                'header' => PublicRecruitmentJobPresenter::header($rawJobs),
                'data' => $data,
            ]);
        } catch (\Throwable $th) {
            Log::error('public_recruitment.jobs.index_failed', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);

            $payload = [
                'status' => false,
                'message' => 'Gagal mengambil daftar lowongan pekerjaan.',
            ];

            if ((bool) env('APP_DEBUG', false)) {
                $payload['error'] = $th->getMessage();
            }

            return $this->jsonResponse($payload, 500);
        }
    }

    public function show(string $no_request): JsonResponse
    {
        try {
            $job = app(PublicRecruitmentOpenJobService::class)->getOpenJob($no_request);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Lowongan tidak ditemukan atau sudah tidak dibuka.',
                ], 404);
            }

            return $this->jsonResponse([
                'status' => true,
                'message' => 'Detail lowongan berhasil diambil.',
                'header' => PublicRecruitmentJobPresenter::header(collect([$job])),
                'data' => PublicRecruitmentJobPresenter::bundle($job),
            ]);
        } catch (\Throwable $th) {
            Log::error('public_recruitment.jobs.show_failed', [
                'no_request' => $no_request,
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);

            $payload = [
                'status' => false,
                'message' => 'Gagal mengambil detail lowongan pekerjaan.',
            ];

            if ((bool) env('APP_DEBUG', false)) {
                $payload['error'] = $th->getMessage();
            }

            return $this->jsonResponse($payload, 500);
        }
    }

    private function jsonResponse(array $payload, int $status = 200): JsonResponse
    {
        $options = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        return response()->json($payload, $status, [], $options);
    }
}
