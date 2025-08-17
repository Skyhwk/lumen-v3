<?php

namespace App\Http\Controllers\api;

use App\Models\ArsipProgrammer;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;




class ArsipController extends Controller
{
    public function index()
    {
        $data = ArsipProgrammer::where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {   
        // dd('masuk');
        try {
            if ($request->hasFile('file_input')) {
                $file = $request->file('file_input');
                $formatterName = str_replace([' ', '/'], '_', $request->nama_file);
                $fileName = $formatterName . time() . '.pdf';
                $file->move(public_path('arsip'), $fileName);
                
                $data = new ArsipProgrammer();
                $data->filename = $fileName;
                $data->keterangan = $request->keterangan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->created_by = $this->karyawan;
                $data->save();
                
                return response()->json([
                    'message' => 'File berhasil diterima dan disimpan!',
                    'file_name' => $fileName,
                ]);
            }
            return response()->json(['error' => 'File tidak ditemukan!'], 400);
        } catch (\Exception $th) {
            dd($th);
            return response()->json(['error' => $th], 400);

        }
    }

    public function delete(Request $request)
    {
        try {
            $data = ArsipProgrammer::findOrFail($request->id);
            $data->is_active = false;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->deleted_by = $this->karyawan;
            $data->save();
            return response()->json([
                'message' => 'File berhasil dihapus dan disimpan!',
            ]);
        } catch (\Exception $th) {
            return response()->json(['error' => $th], 400);
        }
        
    }

}