<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\TemplateBackground;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TemplateBackgroundController extends Controller
{
    public function index(Request $request)
    {
        $template = TemplateBackground::where('is_active', true)->get();
        return Datatables::of($template)->make(true);
    }

    /**
     * Store new template background
     */
    public function store(Request $request)
    {
        try {
            // Cek apakah file ada
            if (!$request->hasFile('input_file')) {
                return response()->json([
                    'message' => 'File tidak ditemukan'
                ], 400);
            }

            $file = $request->file('input_file');
            
            // Generate nama file aman
            $fileName = strtolower(str_replace(' ', '_', $request->nama_template));

            // Simpan file original
            $originalFilename = $this->saveOriginalFile($file, $fileName);

            // Convert dan simpan webp thumbnail
            $webpFilename = $this->convertToWebPFile($fileName, '_thumbnail');

            // Simpan ke database
            $template = TemplateBackground::create([
                'nama_template' => $request->nama_template,
                'thumbnail' => $webpFilename,
                'file' => $originalFilename,
                'is_active' => 1,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            return response()->json([
                'message' => 'Template berhasil ditambahkan',
                'data' => $template
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error saat menyimpan data: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 401);
        }
    }

/**
 * Simpan file original menggunakan move()
 */
    private function saveOriginalFile($file, $fileName)
    {
        $extension = $file->getClientOriginalExtension();
        $safeName = $fileName . '.' . $extension;
        
        $destinationPath = public_path('background-sertifikat');
        
        // Buat folder jika belum ada
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }
        
        // Move file ke destination
        $file->move($destinationPath, $safeName);
        
        return $safeName;
    }

    /**
     * Convert file yang sudah tersimpan ke WebP
     * Dipanggil SETELAH saveOriginalFile()
     */
    private function convertToWebPFile($fileName, $type = '_thumbnail')
    {
        $destinationPath = public_path('background-sertifikat');
        
        // Baca file original yang sudah tersimpan
        $originalFile = $destinationPath . '/' . $fileName . '.*'; // Cari file dengan nama ini
        $files = glob($originalFile);
        
        if (empty($files)) {
            throw new \Exception('File original tidak ditemukan untuk konversi');
        }
        
        $originalFilePath = $files[0]; // Ambil file pertama yang match
        
        // Generate nama file WebP
        $webpName = pathinfo($originalFilePath, PATHINFO_FILENAME) . $type . '.webp';
        
        // Baca binary file
        $binary = file_get_contents($originalFilePath);
        $image = imagecreatefromstring($binary);
        
        if ($image === false) {
            throw new \Exception('File bukan image valid');
        }
        
        // Preserve transparency
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        
        // Convert dan simpan sebagai WebP
        imagewebp($image, $destinationPath . '/' . $webpName, 80);
        imagedestroy($image);
        
        return $webpName;
    }

    public function delete(Request $request)
    {
        try {
            $template = TemplateBackground::find($request->input('id'));

            if (!$template) {
                return response()->json([
                    'message' => 'Template tidak ditemukan'
                ], 404);
            }

            $template->is_active = 0;
            $template->deleted_by = $this->karyawan;
            $template->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $template->save();

            return response()->json([
                'message' => 'Template berhasil dihapus'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error saat menghapus data: ' . $e->getMessage()
            ], 500);
        }
    }


}