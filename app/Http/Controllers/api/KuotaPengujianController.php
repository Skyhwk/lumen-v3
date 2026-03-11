<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\KuotaPengujian;
use App\Models\MasterBakumutu;
use App\Models\MasterKategori;
use App\Models\MasterPelanggan;
use App\Models\MasterRegulasi;
use App\Models\MasterSubKategori;
use App\Models\Parameter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class KuotaPengujianController extends Controller
{
    public function index(Request $request)
    {
        $data = KuotaPengujian::with(['kategori', 'histories'])
            ->where('using_template', $request->use_template)
            ->where('is_active', true);
        return DataTables::of($data)
            ->filterColumn('kategori', function ($query, $keyword) {
                $query->whereHas('kategori', function ($q) use ($keyword) {
                    $q->where('nama_kategori', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('template_data', function ($data) {
                return $data->template_data ? json_decode($data->template_data, true) : null;
            })
            ->make(true);
    }

    public function getPelanggan(Request $request)
    {
        $term = $request->input('term');
        $ext = $request->input('ext');
        $current = $request->input('current');

        $query = MasterPelanggan::where('is_active', true);

        if ($ext) {
            $query = $query->where('id_pelanggan', '<>', $ext);
        }

        if ($term) {
            $query = $query->where(function ($query) use ($term) {
                $query->where('nama_pelanggan', 'LIKE', '%' . $term . '%')
                    ->orWhere('id_pelanggan', 'LIKE', '%' . $term . '%');
            });
        }

        $data = $query->select('id', 'nama_pelanggan', 'id_pelanggan')
            ->limit(50);

        if ($current) {
            $currentPelanggan = MasterPelanggan::where('id_pelanggan', $current)
                ->select('id', 'nama_pelanggan', 'id_pelanggan');
            $data = $data->union($currentPelanggan)->get();
        } else {
            $data = $data->get();
        }

        return response()->json(['data' => $data], 200);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', true)->select('id', 'nama_kategori')->get();
        return response()->json($data);
    }

    public function getSubkategori(Request $request)
    {
        $data = MasterSubKategori::where('is_active', true)->where('id_kategori', $request->id_kategori)->select('id', 'nama_sub_kategori', 'id_kategori')->get();
        return response()->json($data);
    }

    public function getParameter(Request $request)
    {
        try {
            $data = Parameter::with('hargaParameter')
                ->whereHas('hargaParameter')
                ->where('id_kategori', $request->id_kategori)
                ->where('is_active', true)
                ->get();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
                'status' => '500'
            ], 401);
        }
    }

    public function getParameterRegulasi(Request $request)
    {
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

    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::with(['bakumutu'])->where('id_kategori', $request->id_kategori)->where('is_active', true)->get();
        return response()->json($data);
    }

    public function storeKuota(Request $request)
    {
        if($request->use_template === 'true'){ // Template Section
            $response = $this->storeKuotaTemplate($request);
        }else{ // Parameter Section
            $response = $this->storeKuotaParameters($request);
        }

        return response()->json([
            'status' => $response['status'],
            'message' => $response['message'],
            'line' => $response['line'] ?? null,
            'data' => $response['data'] ?? null,
            'file' => $response['file'] ?? null,
        ], $response['code']);
    }
    
    private function storeKuotaTemplate($request)
    {
        DB::beginTransaction();
        try {
            $data_template = (object)[
                'sub_kategori' => $request->sub_kategori,
                'regulasi' => $request->regulasi,
                'parameter' => $request->parameter,
            ];
            $isEdit = false;
            if (isset($request->id)){
                $kuota = KuotaPengujian::find($request->id);
                $kuota->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $kuota->updated_by = $this->karyawan;

                $isEdit = true;
            }else{
                $kuota = new KuotaPengujian();
                $kuota->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $kuota->created_by = $this->karyawan;
            }

            $nama_pelanggan = MasterPelanggan::where('id_pelanggan', $request->id_pelanggan)->first()->nama_pelanggan;
            $kuota->pelanggan_ID    = $request->id_pelanggan;
            $kuota->id_kategori     = $request->id_kategori;
            $kuota->nama_perusahaan = $nama_pelanggan;
            $kuota->template_data   = json_encode($data_template);
            $kuota->jumlah_kuota    = $request->total_kuota;
            if(!$isEdit || ($isEdit && $kuota->is_used == 0)){
                $kuota->sisa        = $request->total_kuota;
            }
            $kuota->tanggal_awal    = explode(';',$request->masa_berlaku)[0];
            $kuota->tanggal_akhir   = explode(';',$request->masa_berlaku)[1];
            $kuota->using_template  = $request->use_template === 'true' ? 1 : 0;
            $kuota->save();
            DB::commit();
            return [
                'status' => 'success',
                'message' => 'Kuota stored successfully using template',
                'code' => 200,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => 'error',
                'message' => 'Failed to store kuota using template: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'code' => 500,
            ];
        }
    }

    private function storeKuotaParameters($request)
    {
        DB::beginTransaction();
        try {
            $isEdit = false;
            if (isset($request->id)){
                $kuota = KuotaPengujian::find($request->id);
                $kuota->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $kuota->updated_by = $this->karyawan;

                $isEdit = true;
            }else{
                $kuota = new KuotaPengujian();
                $kuota->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $kuota->created_by = $this->karyawan;
            }

            // Logic to store kuota using parameters
            $nama_parameter = explode(';', $request->id_parameter)[1];
            $id_parameter = explode(';', $request->id_parameter)[0];
            $nama_perusahaan = explode(' - ', $request->nama_pelanggan)[0];

            $kuota->pelanggan_ID    = $request->id_pelanggan;
            $kuota->id_parameter    = $id_parameter;
            $kuota->id_kategori     = $request->id_kategori;
            $kuota->nama_perusahaan = $nama_perusahaan;
            $kuota->parameter       = $nama_parameter;
            $kuota->jumlah_kuota    = $request->total_kuota;

            if(!$isEdit || ($isEdit && $kuota->is_used == 0)){
                $kuota->sisa        = $request->total_kuota;
            }
            
            $kuota->tanggal_awal    = explode(';',$request->masa_berlaku)[0];
            $kuota->tanggal_akhir   = explode(';',$request->masa_berlaku)[1];
            $kuota->using_template  = $request->use_template === 'true' ? 1 : 0;
            $kuota->save();

            DB::commit();
            return [
                'status' => 'success',
                'message' => 'Kuota stored successfully using parameters',
                'code' => 200,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => 'error',
                'message' => 'Failed to store kuota using parameters: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'code' => 500,
            ];
        }
    }
}