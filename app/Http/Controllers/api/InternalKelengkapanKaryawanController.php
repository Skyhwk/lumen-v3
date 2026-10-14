<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\KelengkapanKaryawanProfileService;
use App\Services\KelengkapanKaryawanSaveService;
use Illuminate\Http\Request;

class InternalKelengkapanKaryawanController extends Controller
{
    public function profile(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Format email tidak valid.'], 422);
        }

        $karyawan = app(KelengkapanKaryawanProfileService::class)->findByEmail($email);
        if (!$karyawan) {
            return response()->json(['message' => 'Email tidak terdaftar sebagai karyawan.'], 422);
        }

        return response()->json(app(KelengkapanKaryawanProfileService::class)->build($karyawan));
    }

    public function save(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Format email tidak valid.'], 422);
        }

        $karyawan = app(KelengkapanKaryawanProfileService::class)->findByEmail($email);
        if (!$karyawan) {
            return response()->json(['message' => 'Email tidak terdaftar sebagai karyawan.'], 422);
        }

        try {
            $profile = app(KelengkapanKaryawanSaveService::class)->save($karyawan, $request, $this->karyawan ?: $karyawan->nama_lengkap);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'status' => 'saved',
            'message' => 'Kelengkapan data karyawan berhasil disimpan.',
            'profile' => $profile,
        ]);
    }
}
