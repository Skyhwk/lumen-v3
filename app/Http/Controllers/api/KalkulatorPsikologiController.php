<?php

namespace App\Http\Controllers\api;

use App\Models\{MasterPelanggan,KalkulatorPsikologi};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class KalkulatorPsikologiController extends Controller
{
    public function index(Request $request)
    {
        $data = KalkulatorPsikologi::where('is_active', 1);

        return datatables()->of($data)
            ->addColumn('divisi', function ($item) {
                return json_decode($item->divisi); // ini buat kasih divisi udah decode
            })
            ->filterColumn('nama_pelanggan', function ($query, $keyword) {
                $query->where('nama_pelanggan', 'like', "%{$keyword}%");
            })
            ->filterColumn('id', function ($query, $keyword) {
                $query->where('id', 'like', "%{$keyword}%");
            })
            // lo bisa tambah filterColumn lain di sini
            ->make(true);
    }



    public function search()
    {
        $data = MasterPelanggan::select('nama_pelanggan')->get();
        return Datatables::of($data)->make(true);
    }

    public function save(Request $request)
    {
        DB::beginTransaction();
        try {
            $namaPelanggan = $request->input('nama_pelanggan');

            $data = KalkulatorPsikologi::create([
                'nama_pelanggan'    => $namaPelanggan,
                'divisi'            => json_encode($request->input('divisi')),
                'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by'        => $this->karyawan
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Success menambahkan data',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message'   => 'Error: ' . $e->getMessage(),
                'line'      => $e->getLine(),
                'file'      => $e->getFile()
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = KalkulatorPsikologi::where('id', $request->id)->where('is_active', 1)->first();
            
            if (!$data) {
                return response()->json([
                    'message' => 'Data tidak ditemukan',
                ], 404);
            }
            
            $data->is_active = 0;
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json([
                'message' => "Success menghapus data $data->nama_pelanggan",
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message'   => 'Error: ' . $e->getMessage(),
                'line'      => $e->getLine()
            ], 500);
        }
    }
}