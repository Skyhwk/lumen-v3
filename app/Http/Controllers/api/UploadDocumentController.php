<?php 

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\UploadDocument;
use Yajra\DataTables\Facades\DataTables;
use Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Carbon\Carbon;

class UploadDocumentController extends Controller
{
    public function index()
    {
        $data = UploadDocument::all();

        return DataTables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file('file_input');
            $originalExtension = $file->getClientOriginalExtension();
            
            // Periksa ekstensi file
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array(strtolower($originalExtension), $allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format file tidak didukung. Hanya PDF, JPG, JPEG, dan PNG yang diizinkan.'
                ], 422);
            }
            
            $path = 'upload_document/';
            $savePath = public_path($path);
            
            // Membuat direktori jika belum ada
            if (!file_exists($savePath)) {
                mkdir($savePath, 0755, true);
            }
            
            // Proses file berdasarkan tipe
            if (strtolower($originalExtension) === 'pdf') {
                // Jika PDF, simpan apa adanya
                $extension = 'pdf';
                $fileName = $this->generateFileName($file, $extension);
                $filePath = $savePath . $fileName;
                $file->move($savePath, $fileName);
            } else {
                // Jika file adalah gambar, konversi ke PDF menggunakan mPDF
                $extension = 'pdf';
                $fileName = $this->generateFileName($file, $extension);
                $this->imageToPdf($file, $path, $fileName);
            }
            
            // Simpan informasi ke database
            $uploadDocument = new UploadDocument();
            $uploadDocument->title = $request->title;
            $uploadDocument->description = $request->description;
            $uploadDocument->filename = $fileName;
            $uploadDocument->created_by = $this->karyawan;
            $uploadDocument->created_at = Carbon::now();
            $uploadDocument->save();
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Dokumen berhasil diunggah',
                'data' => $uploadDocument
            ], 201);
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 401);
        }
    }

    public function delete(Request $request){
        DB::beginTransaction();
        try {
            $id = $request->id;
            $uploadDocument = UploadDocument::find($id);
            if ($uploadDocument) {
                $path = 'upload_document/'.$uploadDocument->filename;
                
                if (file_exists(public_path($path))) {
                    unlink(public_path($path));
                }

                $uploadDocument->delete();

                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Dokumen berhasil dihapus',
                ], 200);
            }else{
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak ditemukan',
                ], 404);
            }
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function generateFileName($file, $extension)
    {
        // Generate a unique filename based on the current timestamp
        $dateMonth = str_pad(date('m'), 2, '0', STR_PAD_LEFT).str_pad(date('d'), 2, '0', STR_PAD_LEFT);

        $fileName = "UPD_".$dateMonth."_".microtime(true).".". $extension;
        $filename = str_replace(' ', '_', $fileName);

        return $filename;
    }

    public function imageToPdf($file, $path, $fileName)
    {
        // Path untuk file gambar sementara
        $tempImagePath = sys_get_temp_dir() . '/' . time() . '.' . $file->getClientOriginalExtension();
        file_put_contents($tempImagePath, file_get_contents($file->getRealPath()));
        
        // Path output untuk file PDF
        $outputPath = public_path($path . $fileName);
        
        // Gunakan mPDF untuk mengkonversi gambar ke PDF
        $mpdf = new Mpdf([
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
        ]);
        
        // Mendapatkan dimensi gambar
        list($width, $height) = getimagesize($tempImagePath);
        
        // Menentukan orientasi halaman berdasarkan dimensi gambar
        if ($width > $height) {
            $mpdf = new Mpdf(['orientation' => 'L']);
        } else {
            $mpdf = new Mpdf(['orientation' => 'P']);
        }
        
        // Menambahkan gambar ke PDF
        $imageData = file_get_contents($tempImagePath);
        $base64Image = base64_encode($imageData);
        $imgType = pathinfo($tempImagePath, PATHINFO_EXTENSION);
        
        $mpdf->WriteHTML('<div style="text-align: center;">
            <img src="data:image/' . $imgType . ';base64,' . $base64Image . '" style="max-width: 100%; height: auto;">
        </div>');
        
        // Simpan PDF
        $mpdf->Output($outputPath, 'F');
        
        // Hapus file sementara
        if (file_exists($tempImagePath)) {
            unlink($tempImagePath);
        }
        
        return true;
    }
}