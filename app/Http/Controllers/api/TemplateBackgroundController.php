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
        
        // Transform thumbnail blob to base64 for frontend display
        $template->transform(function ($item) {
            if ($item->thumbnail) {
                $item->thumbnail_base64 = 'data:image/webp;base64,' . base64_encode($item->thumbnail);
            } else {
                $item->thumbnail_base64 = null;
            }
            return $item;
        });
        
        return DataTables::of($template)->make(true);
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
            
            // Baca file sebagai binary
            $binary = file_get_contents($file->getRealPath());
            $image = imagecreatefromstring($binary);
            
            if ($image === false) {
                return response()->json([
                    'message' => 'File bukan image valid'
                ], 400);
            }

            // Preserve transparency
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);

            // Konversi file original ke WebP (full size)
            ob_start();
            imagewebp($image, null, 85);
            $originalWebp = ob_get_clean();

            // Buat thumbnail (resize)
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);
            $thumbnailWidth = 300; // Lebar thumbnail
            $thumbnailHeight = (int) ($originalHeight * ($thumbnailWidth / $originalWidth));

            $thumbnail = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);
            
            // Preserve transparency untuk thumbnail
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
            imagefill($thumbnail, 0, 0, $transparent);
            
            // Resize image
            imagecopyresampled(
                $thumbnail, $image,
                0, 0, 0, 0,
                $thumbnailWidth, $thumbnailHeight,
                $originalWidth, $originalHeight
            );

            // Konversi thumbnail ke WebP
            ob_start();
            imagewebp($thumbnail, null, 80);
            $thumbnailWebp = ob_get_clean();

            // Bersihkan memory
            imagedestroy($image);
            imagedestroy($thumbnail);

            // Simpan ke database sebagai blob
            $template = TemplateBackground::create([
                'nama_template' => $request->nama_template,
                'thumbnail' => $thumbnailWebp,
                'file' => $originalWebp,
                'is_active' => 1,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            return response()->json([
                'message' => 'Template berhasil ditambahkan',
                'data' => [
                    'id' => $template->id,
                    'nama_template' => $template->nama_template
                ]
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error saat menyimpan data: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 401);
        }
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