<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\JenisFont;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class JenisFontController extends Controller
{
    public function index(Request $request)
    {
        $template = JenisFont::where('is_active', true)->get();
        return Datatables::of($template)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            // Validasi minimal
            if (!$request->has('jenis_font') || !$request->hasFile('font_files')) {
                return response()->json([
                    'message' => 'Jenis font dan file font wajib diisi'
                ], 422);
            }

            $fontFiles = $request->file('font_files');

            // Mapping standar mPDF
            $map = ['R', 'B', 'I', 'BI'];

            $insert = [
                'jenis_font' => $request->jenis_font,
                'font_data'  => json_encode(
                    collect($fontFiles)->mapWithKeys(function ($file, $key) {
                        return [$key => $file->getClientOriginalName()];
                    })
                ),
                'is_active'  => true,
                'created_at'=> Carbon::now(),
                'created_by'=> $this->karyawan,
            ];

            foreach ($map as $key) {
                if (isset($fontFiles[$key])) {
                    $insert['assets_' . strtolower($key)] =
                        file_get_contents($fontFiles[$key]->getRealPath());

                    $insert['mime_' . strtolower($key)] =
                        $fontFiles[$key]->getMimeType();
                }
            }

            $data = JenisFont::create($insert);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Font berhasil disimpan ke database',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $data = JenisFont::where('id', $request->id)->first();
        $data->is_active = false;
        $data->deleted_by = $this->karyawan;
        $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
        $data->save();
        return response()->json([
            'success' => true,
            'message' => 'Font berhasil dihapus',
            'data' => $data
        ], 200);
    }
}