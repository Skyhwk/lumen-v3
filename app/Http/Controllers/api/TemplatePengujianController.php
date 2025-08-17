<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\GetBawahan;
use App\Models\TemplatePenawaran;
use App\Models\Parameter;
use App\Models\{MasterKategori, MasterSubKategori, MasterRegulasi, MasterBakumutu}; // Para Master
use Yajra\DataTables\Facades\DataTables;

class TemplatePengujianController extends Controller
{
    public function index(Request $request){
        $jabatan = $request->attributes->get('user')->karyawan->grade;
        // $data = TemplatePenawaran::where('tipe', $request->tipe)->where('is_active', true);
        $data = TemplatePenawaran::where('tipe', 'non_kontrak');
        if($jabatan == 'SUPERVISOR' || $jabatan == 'MANAGER'){
            $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
            $data->whereIn('created_by', $bawahan);
        }else{
            $data->where('created_by', $this->karyawan);
        }
        $data->where('is_active', true);
        return DataTables::of($data)
            ->editColumn('data_pendukung_sampling', function ($item) {
                return json_decode($item->data_pendukung_sampling);
            })
            ->make(true);
    }

    public function getKategori(Request $request){
        $data = MasterKategori::where('is_active', true)->select('id', 'nama_kategori')->get();
        return response()->json($data);
    }

    public function getSubkategori(Request $request){
        $data = MasterSubKategori::where('is_active', true)->select('id', 'nama_sub_kategori', 'id_kategori')->get();
        return response()->json($data);
    }

    public function getParameter(Request $request){
        try {
            $data = Parameter::with('hargaParameter')
                ->whereHas('hargaParameter')
                ->where('is_active', true)->get();
            // $data = Parameter::where('is_active', true)->get();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
                'status' => '500'
            ], 401);
        }
    }

    public function getParameterRegulasi(Request $request){
        try {
            $idBakumutut = explode('-', $request->id_regulasi);
            $sub_category = explode('-', $request->sub_category);
            $category = explode('-', $request->id_category);

            $bakumutu = MasterBakumutu::where('id_regulasi', $idBakumutut[0])->where('is_active', true)->get();
            $param = array();
            foreach ($bakumutu as $a) {
                array_push($param, $a->id_parameter . ';' . $a->parameter);
            }
            // dd($param);
            /* version 1 */
            $data = Parameter::where('is_active', true)
                ->where('id_kategori', $category[0])
                ->get();

            return response()->json([
                'data' => $data,
                'value' => $param,
                'status' => '200'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
                'status' => '500'
            ], 500);
        }
    }

    public function getRegulasi(Request $request){
        $data = MasterRegulasi::with(['bakumutu'])->where('is_active', true)->get();
        return response()->json($data);
    }

    public function store(Request $request){
        DB::beginTransaction();
        try{
            $data = new TemplatePenawaran();
            $data->nama_template = $request->nama_template;
            $data->tipe = $request->mode;
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Template penawaran berhasil disimpan'
            ], 200);
        }catch(\Exception $e){
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan template penawaran: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function setTemplate(Request $request){
        DB::beginTransaction();
        try{
            $data = TemplatePenawaran::where('id', $request->id)->first();
            $data->data_pendukung_sampling = json_encode($request->data_pendukung_sampling);
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Template penawaran berhasil disimpan'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan template penawaran: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function delete(Request $request){
        DB::beginTransaction();
        try{
            $data = TemplatePenawaran::where('id', $request->id)->first();
            $data->is_active = false;
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Template penawaran berhasil dihapus'
            ], 200);
        }catch(\Exception $e){
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus template penawaran: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}