<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LimsDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Yajra\DataTables\DataTables;

Carbon::setLocale('id');

class LimsFileDocumentController extends Controller
{
    private const STATUS_IN_REVIEW = 'in_review';
    private const STATUS_LEGALIZED = 'legalized';
    private const UPLOAD_DIR = 'uploads/lims/file-documents';
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function index(Request $request)
    {
        $menuSlug = $request->menu_slug;
        $scope = $request->input('scope', 'active');

        if (!$menuSlug) {
            return response()->json(['message' => 'menu_slug wajib diisi'], 422);
        }

        $query = LimsDocument::query()
            ->where('menu_slug', $menuSlug)
            ->where('is_active', true)
            ->whereJsonContains('extra_data->source_type', 'file');

        if ($scope === 'archive') {
            $query->where('status', self::STATUS_LEGALIZED);
        } else {
            $query->where('status', '!=', self::STATUS_LEGALIZED);
        }

        $data = $query->orderBy('created_at', 'desc')->get();

        return DataTables::of($data)
            ->addColumn('status_label', fn() => 'Menunggu Persetujuan')
            ->addColumn('file_type', fn($row) => $row->extra_data['file_type'] ?? 'pdf')
            ->addColumn('disahkan_oleh', fn($row) => $row->pengesahan)
            ->addColumn('uploaded_at', fn($row) => $row->created_at)
            ->make(true);
    }

    public function saveDocument(Request $request)
    {
        if (!$request->filled('menu_slug')) {
            return response()->json(['message' => 'menu_slug wajib diisi'], 422);
        }

        $files = $request->file('file_input');

        if (!$files) {
            return response()->json(['message' => 'File dokumen wajib diupload'], 422);
        }

        if (!is_array($files)) {
            $files = [$files];
        }

        DB::beginTransaction();

        try {
            $menuSlug = $request->menu_slug;
            $destinationDir = base_path('public/' . self::UPLOAD_DIR . '/' . $menuSlug);

            if (!File::isDirectory($destinationDir)) {
                File::makeDirectory($destinationDir, 0777, true);
            }

            $savedCount = 0;

            foreach ($files as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }

                $extension = strtolower($file->getClientOriginalExtension());
                $originalName = $file->getClientOriginalName();
                $mimeType = $file->getClientMimeType();

                if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                    continue;
                }

                $noDokumen = pathinfo($originalName, PATHINFO_FILENAME);
                $cleanFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $noDokumen);
                $storedFilename = $cleanFilename . '_' . time() . '_' . uniqid() . '.' . $extension;
                $file->move($destinationDir, $storedFilename);

                $relativePath = self::UPLOAD_DIR . '/' . $menuSlug . '/' . $storedFilename;
                $fileType = in_array($extension, self::IMAGE_EXTENSIONS, true) ? 'image' : 'pdf';

                $document = new LimsDocument();
                $document->menu_slug = $menuSlug;
                $document->no_dokumen = $noDokumen;
                $document->nama_dokumen = $noDokumen;
                $document->content_file = $relativePath;
                $document->extra_data = [
                    'source_type' => 'file',
                    'file_type' => $fileType,
                    'original_name' => $originalName,
                    'mime_type' => $mimeType,
                ];
                $document->status = self::STATUS_IN_REVIEW;
                $document->is_active = true;
                $document->created_by = $this->karyawan;
                $document->created_at = Carbon::now();
                $document->save();

                $savedCount++;
            }

            if ($savedCount === 0) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Tidak ada file valid yang berhasil diupload. Gunakan PDF atau gambar (jpg, png, gif, webp)',
                ], 422);
            }

            DB::commit();

            return response()->json([
                'message' => $savedCount . ' dokumen berhasil diupload',
                'count' => $savedCount,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function viewDocument(Request $request)
    {
        $document = $this->findActiveDocument($request->id, $request->menu_slug);

        if (!$document) {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        $filePath = base_path('public/' . $document->content_file);

        if (!File::exists($filePath)) {
            return response()->json(['message' => 'File dokumen tidak ditemukan'], 404);
        }

        $extraData = $document->extra_data ?? [];
        $mimeType = $extraData['mime_type'] ?? mime_content_type($filePath) ?: 'application/octet-stream';

        return response()->json([
            'data' => base64_encode(File::get($filePath)),
            'mime_type' => $mimeType,
            'file_type' => $extraData['file_type'] ?? 'pdf',
            'original_name' => $extraData['original_name'] ?? basename($document->content_file),
            'message' => 'Dokumen berhasil dimuat',
        ], 200);
    }

    public function approveDocument(Request $request)
    {
        $document = $this->findActiveDocument($request->id, $request->menu_slug);

        if (!$document) {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        if ($document->status === self::STATUS_LEGALIZED) {
            return response()->json(['message' => 'Dokumen sudah diarsipkan'], 422);
        }

        $karyawan = $request->attributes->get('user')->karyawan ?? null;
        $approverName = $karyawan->nama_lengkap ?? $this->karyawan ?? 'System';

        DB::beginTransaction();

        try {
            $document->status = self::STATUS_LEGALIZED;
            $document->pengesahan = $approverName;
            $document->disahkan_pada = Carbon::today()->format('Y-m-d');
            $document->tanggal_pengesahan = Carbon::today()->format('Y-m-d');
            $document->updated_by = $this->karyawan;
            $document->updated_at = Carbon::now();
            $document->save();

            DB::commit();

            return response()->json(['message' => 'Dokumen berhasil diarsipkan'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function rejectDocument(Request $request)
    {
        $document = $this->findActiveDocument($request->id, $request->menu_slug);

        if (!$document) {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        if ($document->status !== self::STATUS_LEGALIZED) {
            return response()->json(['message' => 'Hanya dokumen arsip yang dapat dikembalikan ke dokumen aktif'], 422);
        }

        DB::beginTransaction();

        try {
            $document->status = self::STATUS_IN_REVIEW;
            $document->pengesahan = null;
            $document->disahkan_pada = null;
            $document->tanggal_pengesahan = null;
            $document->updated_by = $this->karyawan;
            $document->updated_at = Carbon::now();
            $document->save();

            DB::commit();

            return response()->json(['message' => 'Dokumen berhasil dikembalikan ke dokumen aktif'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function deleteDocument(Request $request)
    {
        $document = $this->findActiveDocument($request->id, $request->menu_slug);

        if (!$document) {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        if ($document->status === self::STATUS_LEGALIZED) {
            return response()->json(['message' => 'Dokumen arsip tidak dapat dihapus dari sini'], 422);
        }

        DB::beginTransaction();

        try {
            $contentFile = $document->content_file;

            $document->is_active = false;
            $document->deleted_by = $this->karyawan;
            $document->deleted_at = Carbon::now();
            $document->save();

            $this->deleteUploadedFile($contentFile);

            DB::commit();

            return response()->json(['message' => 'Dokumen berhasil dihapus'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    private function findActiveDocument($id, $menuSlug)
    {
        if (!$id || !$menuSlug) {
            return null;
        }

        return LimsDocument::where('id', $id)
            ->where('menu_slug', $menuSlug)
            ->where('is_active', true)
            ->whereJsonContains('extra_data->source_type', 'file')
            ->first();
    }

    private function deleteUploadedFile(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }

        $fullPath = base_path('public/' . $relativePath);

        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }
}
