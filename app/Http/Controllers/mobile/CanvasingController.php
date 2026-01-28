<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

use App\Models\Canvasing;
use App\Models\MasterWilayahSampling;

class CanvasingController extends Controller
{
    public function index(Request $request){
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = Canvasing::where('created_by', $this->karyawan)
            ->where('is_active', 1)
            ->where('is_processed', 0);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama_perusahaan', 'like', "%$search%")
                ->orWhere('nama_pic', 'like', "%$search%")
                ->orWhere('wilayah', 'like', "%$search%");
            });
        }

        $data = $query->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($data);
    }

    public function store(Request $request){
        try {
            DB::beginTransaction();
            $data = new Canvasing();
            $data->nama_perusahaan = $request->nama_perusahaan;
            $data->no_telpon = $request->no_telpon;
            $data->nama_pic = $request->nama_pic;
            $data->nama_petugas = $this->karyawan;
            $data->no_hp_pic = $request->no_hp_pic;
            $data->wilayah = $request->wilayah;
            $data->penerima_flyer = $request->penerima_flyer;
            $data->latitude = $request->latitude;
            $data->longitude = $request->longitude;
            $data->titik_koordinat = $request->titik_koordinat;
            $data->jumlah_flyer = $request->jumlah_flyer;
            $data->foto_1                 = $request->foto_lokasi_1 ? self::convertImg($request->foto_lokasi_1, 1, $this->user_id) : null;
            $data->foto_2                 = $request->foto_lokasi_2 ? self::convertImg($request->foto_lokasi_2, 2, $this->user_id) : null;
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();

            return response()->json([
                'message' => 'Berhasil Simpan Data Canvasing', 
                'success' => true
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(), 
                'success' => false
            ], 500);
        }
    }

    public function delete(Request $request){
        try {
            DB::beginTransaction();
            $data = Canvasing::where('id', $request->id)->first();
            $data->is_active = 0;
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();

            return response()->json([
                'message' => 'Berhasil Hapus Data Canvasing', 
                'success' => true
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(), 
                'success' => false
            ], 500);
        }
    }

    public function getWilayah(){
        $wilayah = MasterWilayahSampling::where('is_active', true)
            ->select('id', 'wilayah')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $wilayah,
            'message' => 'Available wilayah data retrieved successfully',
        ], 201);
    }

    public function convertImg($foto = '', $type = '', $user = '')
    {
        
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        $safeName = DATE('YmdHis') . '_' . $user . $type . '.jpeg';
        $destinationPath = public_path() . '/dokumentasi/canvasing/';

        // Jika folder belum ada, buat folder
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        $success = file_put_contents($destinationPath . $safeName, $file);

        return $safeName;
    }

}