<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Services\KaryawanArsipDokumenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ArsipKaryawanController extends Controller
{
    protected $service;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->service = new KaryawanArsipDokumenService();
    }

    public function index(Request $request)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
        ]);

        $karyawan = MasterKaryawan::where('id', $request->karyawan_id)->where('is_active', 1)->first();
        if (!$karyawan) {
            return response()->json(['message' => 'Karyawan tidak ditemukan.'], 404);
        }

        $documents = $this->service->listByKaryawan($karyawan->id);

        return response()->json([
            'message' => 'Data arsip dokumen karyawan berhasil dimuat.',
            'data' => [
                'karyawan' => [
                    'id' => $karyawan->id,
                    'nik_karyawan' => $karyawan->nik_karyawan,
                    'nama_lengkap' => $karyawan->nama_lengkap,
                ],
                'documents' => $documents,
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
            'jenis_dokumen' => 'required|string|max:100',
        ]);

        if (!$request->hasFile('file')) {
            return response()->json(['message' => 'File dokumen wajib diunggah.'], 422);
        }

        $karyawan = MasterKaryawan::where('id', $request->karyawan_id)->where('is_active', 1)->first();
        if (!$karyawan) {
            return response()->json(['message' => 'Karyawan tidak ditemukan.'], 404);
        }

        if (!Schema::hasTable('karyawan_dokumen_arsip')) {
            return response()->json(['message' => 'Tabel arsip dokumen karyawan belum tersedia. Jalankan migrasi terlebih dahulu.'], 500);
        }

        try {
            $document = $this->service->storeUploadedFile(
                $karyawan->id,
                $request->file('file'),
                $request->jenis_dokumen,
                $this->karyawan,
                $request->catatan
            );

            return response()->json([
                'message' => 'Dokumen arsip karyawan berhasil diunggah.',
                'data' => $document,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|integer',
            'karyawan_id' => 'required|integer',
        ]);

        $deleted = $this->service->deleteDocument($request->id, $request->karyawan_id);
        if (!$deleted) {
            return response()->json(['message' => 'Dokumen arsip tidak ditemukan.'], 404);
        }

        return response()->json(['message' => 'Dokumen arsip karyawan berhasil dihapus.'], 200);
    }
}
