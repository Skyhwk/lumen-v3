<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Services\EmployeeDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Yajra\Datatables\Datatables;

class EmployeeDocumentController extends Controller
{
    protected $service;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->service = new EmployeeDocumentService();
    }

    public function index(Request $request)
    {
        $data = MasterKaryawan::query()
            ->where('is_active', true)
            ->where('grade', '!=', 'DIRECTOR')
            ->withCount(['employeeDocuments as dokumen_count' => function ($query) {
                $query->where('is_active', 1);
            }]);

        return Datatables::of($data)->make(true);
    }

    public function documentTypes()
    {
        return response()->json([
            'message' => 'Jenis dokumen berhasil dimuat.',
            'data' => $this->service->documentTypes(),
        ], 200);
    }

    public function listByKaryawan(Request $request)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
        ]);

        $karyawan = MasterKaryawan::where('id', $request->karyawan_id)->first();
        if (!$karyawan) {
            return response()->json(['message' => 'Karyawan tidak ditemukan.'], 404);
        }

        return response()->json([
            'message' => 'Dokumen karyawan berhasil dimuat.',
            'data' => [
                'karyawan' => [
                    'id' => $karyawan->id,
                    'nik_karyawan' => $karyawan->nik_karyawan,
                    'nama_lengkap' => $karyawan->nama_lengkap,
                    'department' => $karyawan->department,
                ],
                'documents' => $this->service->listByKaryawan($karyawan->id),
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
            'document_type' => 'required|string|max:50',
        ]);

        if (!$request->hasFile('file')) {
            return response()->json(['message' => 'File dokumen wajib diunggah.'], 422);
        }

        $karyawan = MasterKaryawan::where('id', $request->karyawan_id)->first();
        if (!$karyawan) {
            return response()->json(['message' => 'Karyawan tidak ditemukan.'], 404);
        }

        if (!Schema::hasTable('employee_documents')) {
            return response()->json(['message' => 'Tabel employee_documents belum tersedia.'], 500);
        }

        try {
            $document = $this->service->storeUploadedFile(
                $karyawan->id,
                $request->file('file'),
                $request->document_type,
                $this->karyawan
            );

            return response()->json([
                'message' => 'Dokumen karyawan berhasil diunggah.',
                'data' => $document,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function download(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|integer',
            'karyawan_id' => 'required|integer',
        ]);

        $file = $this->service->resolveDownload($request->id, $request->karyawan_id);
        if (!$file) {
            return response()->json(['message' => 'Dokumen tidak ditemukan.'], 404);
        }

        return response()->download($file['path'], $file['name']);
    }

    public function downloadAll(Request $request)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
        ]);

        $karyawan = MasterKaryawan::where('id', $request->karyawan_id)->first();
        if (!$karyawan) {
            return response()->json(['message' => 'Karyawan tidak ditemukan.'], 404);
        }

        try {
            $zip = $this->service->buildZip($karyawan->id, $karyawan->nik_karyawan ?: $karyawan->nama_lengkap);
            return response()->download($zip['path'], $zip['name'])->deleteFileAfterSend(true);
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
            return response()->json(['message' => 'Dokumen tidak ditemukan.'], 404);
        }

        return response()->json(['message' => 'Dokumen karyawan berhasil dihapus.'], 200);
    }
}
