<?php

namespace App\Http\Controllers\Greatday;

use App\Services\Greatday\FormsPendingApprovalsService;
use App\Services\Hr\HrTableMode;
use Illuminate\Http\Request;

class HomePageController extends Controller
{
    public function getFoto($image)
    {
        $v3Path = rtrim(config('greatday.foto_karyawan_path'), '/\\');
        $getFile = $v3Path . DIRECTORY_SEPARATOR . $image;

        if (!file_exists($getFile)) {
            return response()->json(['message' => 'File tidak ditemukan'], 404);
        }

        return $this->respondImageWebp($getFile, $image);
    }

    public function getFotoAbsen($image)
    {
        $getFile = \App\Support\Greatday\GreatdayAssetPaths::resolveAbsensiFilePath($image);

        if ($getFile === null || !is_file($getFile)) {
            return response()->json(['message' => 'File tidak ditemukan'], 404);
        }

        return $this->respondImageWebp($getFile, $image);
    }

    /** Antrian approve atasan saja (Manager/Supervisor, status Pending). */
    public function pendingApprovals(Request $request)
    {
        try {
            $user = $this->karyawan;
            if (!$user) {
                return response()->json([
                    'data' => [],
                    'message' => 'Pending approvals retrieved successfully',
                ], 200);
            }

            $allPending = app(FormsPendingApprovalsService::class)->pendingItems($user);

            return response()->json([
                'data' => $allPending,
                'message' => 'Pending approvals retrieved successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Failed to load pending approvals: ' . $th->getMessage(),
            ], 500);
        }
    }

    private function respondImageWebp(string $getFile, string $image)
    {
        $ext = strtolower(pathinfo($getFile, PATHINFO_EXTENSION));

        if ($ext === 'webp') {
            return response(file_get_contents($getFile), 200, [
                'Content-Type' => 'image/webp',
                'Content-Disposition' => 'inline; filename="' . pathinfo($image, PATHINFO_FILENAME) . '.webp"',
            ]);
        }

        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $img = imagecreatefromjpeg($getFile);
                break;
            case 'png':
                $img = imagecreatefrompng($getFile);
                break;
            default:
                return response()->json(['message' => 'Format gambar tidak didukung'], 400);
        }

        ob_start();
        imagewebp($img, null, 80);
        $imageData = ob_get_clean();
        imagedestroy($img);

        return response($imageData, 200)
            ->header('Content-Type', 'image/webp')
            ->header('Content-Disposition', 'inline; filename="' . pathinfo($image, PATHINFO_FILENAME) . '.webp"');
    }
}
