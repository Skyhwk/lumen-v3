<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterBakumutu;
use App\Models\MasterKategori;
use App\Models\MasterRegulasi;
use App\Models\MasterSubKategori;
use App\Models\Parameter;
use App\Models\TemplatePaketAnalisa;
use App\Services\GetBawahan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Para Master
use Yajra\DataTables\Facades\DataTables;

class TemplatePaketAnalisaController extends Controller
{
    public function index(Request $request)
    {
        $data = TemplatePaketAnalisa::where('is_active', true);

        return DataTables::of($data)
            ->editColumn('data_pendukung_sampling', function ($item) {
                $data = json_decode($item->data_pendukung_sampling, true);

                if (empty($data) || ! is_array($data)) {
                    return [];
                }

                foreach ($data as $i => &$row) {
                    $row['id_x'] = str_replace('.', '', microtime(true)) . ($i + 1);
                }

                return $data;
            })
            ->addColumn('harga_paket', function ($item) {
                $data = json_decode($item->data_pendukung_sampling, true);
                $harga_paket = 0;
                if (empty($data) || ! is_array($data)) {
                    return 0;
                }

                foreach ($data as $i => &$row) {
                    $harga_paket += $row['harga_paket'];
                }
                return $harga_paket;
            })
            ->make(true);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', true)
            ->select('id', 'nama_kategori')
            ->get();

        // Tambahkan Multi Kategori di paling atas
        $data->prepend([
            'id' => 0,
            'nama_kategori' => 'Multi Kategori'
        ]);

        return response()->json($data);
    }

    public function getSubkategori(Request $request)
    {
        $data = MasterSubKategori::where('is_active', true)->select('id', 'nama_sub_kategori', 'id_kategori')->get();
        return response()->json($data);
    }

    public function getParameter(Request $request)
    {
        try {
            $data = Parameter::with('hargaParameter')
                ->whereHas('hargaParameter')
                ->where('is_active', true)->get();
            // $data = Parameter::where('is_active', true)->get();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
                'status'  => '500',
            ], 401);
        }
    }

    public function getParameterRegulasi(Request $request)
    {
        try {
            $idBakumutut  = explode('-', $request->id_regulasi);
            $sub_category = explode('-', $request->sub_category);
            $category     = explode('-', $request->id_category);

            $bakumutu = MasterBakumutu::where('id_regulasi', $idBakumutut[0])->where('is_active', true)->get();
            $param    = [];
            foreach ($bakumutu as $a) {
                array_push($param, $a->id_parameter . ';' . $a->parameter);
            }
            // dd($param);
            /* version 1 */
            $data = Parameter::where('is_active', true)
                ->where('id_kategori', $category[0])
                ->get();

            return response()->json([
                'data'   => $data,
                'value'  => $param,
                'status' => '200',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
                'status'  => '500',
            ], 500);
        }
    }

    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::with(['bakumutu'])->where('is_active', true)->get();
        return response()->json($data);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data                = new TemplatePaketAnalisa();
            $data->nama_template = $request->nama_template;
            $data->kategori      = $request->kategori;
            $data->sub_kategori  = $request->sub_kategori;
            $data->created_by    = $this->karyawan;
            $data->created_at    = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Template penawaran berhasil disimpan',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan template penawaran: ' . $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ], 500);
        }
    }

    public function setTemplate(Request $request)
    {
        DB::beginTransaction();
        try {
            $data                          = TemplatePaketAnalisa::where('id', $request->id)->first();
            $data->data_pendukung_sampling = json_encode($request->data_pendukung_sampling);
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Template penawaran berhasil disimpan',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan template penawaran: ' . $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            $data            = TemplatePaketAnalisa::where('id', $request->id)->first();
            $data->is_active = false;
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Template penawaran berhasil dihapus',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus template penawaran: ' . $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ], 500);
        }
    }
}
