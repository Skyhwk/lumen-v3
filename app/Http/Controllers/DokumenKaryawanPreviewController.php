<?php

namespace App\Http\Controllers;

use App\Models\MasterKaryawan;
use App\Models\NewRecruitment;
use App\Services\GenerateDokumenKaryawanService;
use Illuminate\Http\Request;

class DokumenKaryawanPreviewController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureLocalRequest($request);

        return view('DokumenKaryawan.sprj-preview', [
            'karyawanId' => $this->resolveId(
                $request->query('karyawan_id'),
                MasterKaryawan::query()->orderByDesc('id')->value('id')
            ),
            'recruitmentId' => $this->resolveId(
                $request->query('new_recruitment_id'),
                NewRecruitment::query()->orderByDesc('id')->value('id')
            ),
        ]);
    }

    public function pdf(Request $request, GenerateDokumenKaryawanService $service)
    {
        $this->ensureLocalRequest($request);

        $karyawanId = $this->resolveId($request->query('karyawan_id'));
        $recruitmentId = $this->resolveId($request->query('new_recruitment_id'));

        if (!$karyawanId || !$recruitmentId) {
            return response(
                'karyawan_id dan new_recruitment_id wajib berupa angka lebih dari 0.',
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        try {
            $content = $service->previewSprj($karyawanId, $recruitmentId);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview-sprj.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            return response(
                'Preview gagal dibuat: ' . $e->getMessage(),
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
    }

    public function cocPdf(Request $request, GenerateDokumenKaryawanService $service)
    {
        $this->ensureLocalRequest($request);

        $karyawanId = $this->resolveId($request->query('karyawan_id'));
        $recruitmentId = $this->resolveId($request->query('new_recruitment_id'));

        if (!$karyawanId || !$recruitmentId) {
            return response(
                'karyawan_id dan new_recruitment_id wajib berupa angka lebih dari 0.',
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        try {
            $content = $service->previewCoc($karyawanId, $recruitmentId);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview-coc.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            return response(
                'Preview gagal dibuat: ' . $e->getMessage(),
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
    }

    public function pkkpPdf(Request $request, GenerateDokumenKaryawanService $service)
    {
        $this->ensureLocalRequest($request);

        $karyawanId = $this->resolveId($request->query('karyawan_id'));
        $recruitmentId = $this->resolveId($request->query('new_recruitment_id'));

        if (!$karyawanId || !$recruitmentId) {
            return response(
                'karyawan_id dan new_recruitment_id wajib berupa angka lebih dari 0.',
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        try {
            $content = $service->previewPkkp($karyawanId, $recruitmentId);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview-pkkp.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            return response(
                'Preview gagal dibuat: ' . $e->getMessage(),
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
    }

    public function spkbPdf(Request $request, GenerateDokumenKaryawanService $service)
    {
        $this->ensureLocalRequest($request);

        $karyawanId = $this->resolveId($request->query('karyawan_id'));
        $recruitmentId = $this->resolveId($request->query('new_recruitment_id'));

        if (!$karyawanId || !$recruitmentId) {
            return response(
                'karyawan_id dan new_recruitment_id wajib berupa angka lebih dari 0.',
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        try {
            $content = $service->previewSpkb($karyawanId, $recruitmentId);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview-spkb.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            return response(
                'Preview gagal dibuat: ' . $e->getMessage(),
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
    }

    public function spnyPdf(Request $request, GenerateDokumenKaryawanService $service)
    {
        $this->ensureLocalRequest($request);

        $karyawanId = $this->resolveId($request->query('karyawan_id'));
        $recruitmentId = $this->resolveId($request->query('new_recruitment_id'));

        if (!$karyawanId || !$recruitmentId) {
            return response(
                'karyawan_id dan new_recruitment_id wajib berupa angka lebih dari 0.',
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        try {
            $content = $service->previewSpny($karyawanId, $recruitmentId);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview-spny.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            return response(
                'Preview gagal dibuat: ' . $e->getMessage(),
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
    }

    public function ndaPdf(Request $request, GenerateDokumenKaryawanService $service)
    {
        $this->ensureLocalRequest($request);

        $karyawanId = $this->resolveId($request->query('karyawan_id'));
        $recruitmentId = $this->resolveId($request->query('new_recruitment_id'));

        if (!$karyawanId || !$recruitmentId) {
            return response(
                'karyawan_id dan new_recruitment_id wajib berupa angka lebih dari 0.',
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        try {
            $content = $service->previewNda($karyawanId, $recruitmentId);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview-nda.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            return response(
                'Preview gagal dibuat: ' . $e->getMessage(),
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
    }

    public function ndaSpvPdf(Request $request, GenerateDokumenKaryawanService $service)
    {
        $this->ensureLocalRequest($request);

        $karyawanId = $this->resolveId($request->query('karyawan_id'));
        $recruitmentId = $this->resolveId($request->query('new_recruitment_id'));

        if (!$karyawanId || !$recruitmentId) {
            return response(
                'karyawan_id dan new_recruitment_id wajib berupa angka lebih dari 0.',
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        try {
            $content = $service->previewNdaSpv($karyawanId, $recruitmentId);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview-nda-spv.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            return response(
                'Preview gagal dibuat: ' . $e->getMessage(),
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
    }

    public function pkppPdf(Request $request, GenerateDokumenKaryawanService $service)
    {
        $this->ensureLocalRequest($request);

        $karyawanId = $this->resolveId($request->query('karyawan_id'));
        $recruitmentId = $this->resolveId($request->query('new_recruitment_id'));

        if (!$karyawanId || !$recruitmentId) {
            return response(
                'karyawan_id dan new_recruitment_id wajib berupa angka lebih dari 0.',
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        try {
            $content = $service->previewPkpp($karyawanId, $recruitmentId);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview-pkpp.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            return response(
                'Preview gagal dibuat: ' . $e->getMessage(),
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
    }

    public function pkppSamplingPdf(Request $request, GenerateDokumenKaryawanService $service)
    {
        $this->ensureLocalRequest($request);

        $karyawanId = $this->resolveId($request->query('karyawan_id'));
        $recruitmentId = $this->resolveId($request->query('new_recruitment_id'));

        if (!$karyawanId || !$recruitmentId) {
            return response(
                'karyawan_id dan new_recruitment_id wajib berupa angka lebih dari 0.',
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        try {
            $content = $service->previewPkppSampling($karyawanId, $recruitmentId);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview-pkpp-sampling.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            return response(
                'Preview gagal dibuat: ' . $e->getMessage(),
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
    }

    public function version(Request $request)
    {
        $this->ensureLocalRequest($request);

        $files = [
            resource_path('views/DokumenKaryawan/sprj.blade.php'),
            base_path('app/Services/GenerateDokumenKaryawanService.php'),
        ];

        $version = sha1(implode('|', array_map(function (string $file): string {
            return is_file($file) ? (string) sha1_file($file) : '';
        }, $files)));

        return response()->json(['version' => $version], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function resolveId($value, $fallback = null): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id !== false) {
            return (int) $id;
        }

        return $fallback ? (int) $fallback : null;
    }

    private function ensureLocalRequest(Request $request): void
    {
        if (
            !app()->environment('local')
            || !in_array($request->ip(), ['127.0.0.1', '::1'], true)
        ) {
            abort(404);
        }
    }
}
