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
            $file = $request->file('font_file');

            // Generate nama aman
            $fileName =  $file->getClientOriginalName();

            // Folder tujuan
            $destinationPath = public_path('fonts');

            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            // Simpan file
            $file->move($destinationPath, $fileName);

            // Simpan ke DB (HANYA STRING)
            $data = JenisFont::create([
                'jenis_font' => $request->nama_font,
                'filename' => $fileName,
                'is_active' => true,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $this->karyawan,    
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Font berhasil ditambahkan',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
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