<?php
namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\AssessmentInternal;
use App\Models\QuestionCategory;

class AssessmentInternalController extends Controller
{
    public function index(Request $request)
    {
        $data = AssessmentInternal::query()->orderBy('id', 'desc');

        return datatables()->of($data)->make(true);
    }

    public function store(Request $request)
    {
        try {
            if (empty($request->nama_assesment)) {
                return response()->json(['message' => 'Nama Assessment harus diisi!'], 400);
            }
            $nama = $request->nama_assesment;
            
            // Cek apakah assessment dengan nama tersebut sudah ada
            $exists = AssessmentInternal::where('nama_assesment', $nama)->first();
            if ($exists) {
                return response()->json(['message' => 'Assessment dengan nama "' . $nama . '" sudah dibuat sebelumnya!'], 400);
            }

            // Generate string unik 8 karakter huruf kapital dan angka
            $pool = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $batch = substr(str_shuffle(str_repeat($pool, 8)), 0, 8);
            
            while (AssessmentInternal::where('batch', $batch)->exists()) {
                $batch = substr(str_shuffle(str_repeat($pool, 8)), 0, 8);
            }

            $hrdName = $this->karyawan ?? 'HRD';

            $assessment = new AssessmentInternal();
            $assessment->batch = $batch;
            $assessment->nama_assesment = $nama;
            $assessment->link_qr = null;
            $assessment->created_by = $hrdName;
            
            // Mengisi timestamps secara manual karena di model di-set public $timestamps = false
            $assessment->created_at = date('Y-m-d H:i:s');
            $assessment->updated_at = date('Y-m-d H:i:s');
            
            $assessment->save();

            return response()->json(['message' => 'Assessment "' . $nama . '" berhasil dibuat dengan Batch ' . $batch], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1] ?? null;
            if ($errorCode == 1062) {
                return response()->json(['message' => 'Gagal membuat assessment: Duplikasi Batch. Silakan coba lagi.'], 400);
            }
            return response()->json(['message' => 'Terjadi kesalahan database: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function updateLink(Request $request)
    {
        try {
            $assessment = AssessmentInternal::find($request->id);
            if (!$assessment) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $token = bin2hex(random_bytes(32));
            while (AssessmentInternal::where('token', $token)->where('id', '!=', $assessment->id)->exists()) {
                $token = bin2hex(random_bytes(32));
            }

            // Format URL Assessment
            $baseUrl = env('PORTALV4');
            $assessment->token = $token;
            $assessment->link_qr = $baseUrl . '/private/assessment/' . $token;
            $assessment->is_link_active = true;
            
            $assessment->save();

            return response()->json(['message' => 'Link berhasil di-generate secara otomatis!'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function takedownLink(Request $request)
    {
        try {
            $assessment = AssessmentInternal::find($request->id);
            if (!$assessment) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $assessment->is_link_active = false;
            $assessment->link_deactivated_at = date('Y-m-d H:i:s');
            $assessment->save();

            return response()->json(['message' => 'Link assessment berhasil di-take down!'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function getCategories(Request $request)
    {
        try {
            $categories = QuestionCategory::where('category_scope', 'hr')->get(['id', 'name']);
            return response()->json(['data' => $categories], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function publish(Request $request)
    {
        try {
            if (empty($request->category_question) || !is_array($request->category_question)) {
                return response()->json(['message' => 'Kategori soal harus dipilih!'], 400);
            }

            $assessment = AssessmentInternal::find($request->id);
            if (!$assessment) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $assessment->category_question = $request->category_question;
            $assessment->is_publish = true;
            $assessment->save();

            return response()->json(['message' => 'Assessment berhasil dipublish dan kategori tersimpan'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function cancel(Request $request)
    {
        try {
            $assessment = AssessmentInternal::find($request->id);
            if (!$assessment) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $hrdName = $this->karyawan ?? 'HRD';

            $assessment->canceled_by = $hrdName;
            $assessment->canceled_at = date('Y-m-d H:i:s');
            $assessment->save();

            return response()->json(['message' => 'Assessment berhasil dibatalkan'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function generateQr(Request $request)
    {
        try {
            $assessment = AssessmentInternal::find($request->id);
            if (!$assessment) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            if (empty($assessment->link_qr)) {
                return response()->json(['message' => 'Link QR tidak tersedia, tidak bisa di-generate.'], 400);
            }

            $fileName = 'QR_' . $assessment->batch . '_' . time() . '.png';
            $path = base_path('public/QR_Assessment');
            
            if (!file_exists($path)) {
                mkdir($path, 0775, true);
            }

            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(300)->generate($assessment->link_qr, $path . '/' . $fileName);

            $assessment->image_qr = $fileName;
            $assessment->save();

            return response()->json(['message' => 'QR Code berhasil di-generate', 'file' => $fileName], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }
}
