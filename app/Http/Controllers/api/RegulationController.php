<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\CompanyRegulasi;
use App\Models\MasterRegulasi;
use App\Models\Service;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use DB;
use Carbon\Carbon;

class RegulationController extends Controller
{
    public function index(Request $request)
    {
        $regulation = CompanyRegulasi::get();
        $regulation->map(function ($item) {
            $item->parameters = json_decode($item->parameters);
            return $item;
        });
        return DataTables::of($regulation)->make(true);
    }

    public function getService(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = Service::where('is_active', 1)->get();
            // dd('masuk');
            DB::commit();
            return response()->json([
                'data' => $data,
                'status' => 200,
                'message' => 'Regulasi berhasil Dapatkan.'
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'status' => 500
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id != null || $request->id != '') {
                $params = explode(', ', $request->parameters);
                $regulation = CompanyRegulasi::find($request->id);
                $regulation->name = $request->name;
                $regulation->parameters = json_encode($params);
                $regulation->service = $request->service;
                $regulation->updated_at = Carbon::now();
                $regulation->updated_by = $this->karyawan;
                $regulation->save();
            } else {
                $params = explode(', ', $request->parameters);
                $regulation = new CompanyRegulasi;
                $regulation->name = $request->name;
                $regulation->parameters = json_encode($params);
                $regulation->service = $request->service;
                $regulation->created_at = Carbon::now();
                $regulation->created_by = $this->karyawan;
                $regulation->save();
            }
            DB::commit();
            return response()->json([
                'message' => 'Regulasi berhasil disimpan.',
                'status' => '200'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            $regulation = CompanyRegulasi::find($request->id);
            $regulation->delete();
            DB::commit();
            return response()->json([
                'message' => 'Regulasi berhasil dihapus.',
                'status' => '200'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::with('bakumutu:id_regulasi,parameter,method')
            ->whereHas('bakumutu')
            ->select('id', 'nama_kategori', 'peraturan')
            ->where('is_active', true)
            ->get()
            ->groupBy('nama_kategori');

        return response()->json($data, 200);
    }
}
