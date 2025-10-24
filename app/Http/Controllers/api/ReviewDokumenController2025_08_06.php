<?php

namespace App\Http\Controllers\api;

use App\Models\{
    MasterKategori,
    MasterSubKategori,
    MasterRegulasi,
    OrderDetail,
    Parameter,
    MasterBakumutu,
    TcOrderDetail
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Illuminate\Database\Eloquent\Collection;

class ReviewDokumenController extends Controller
{

    public function index()
    {
        $expiredNamaLabs = Parameter::where('is_expired', 1)
            ->pluck('nama_lab')
            ->toArray();

        if (empty($expiredNamaLabs)) {
            $data = collect();
        } else {
            $data = OrderDetail::where('is_active', true)
                ->whereDate('tanggal_sampling', '>', Carbon::today())
                ->orderBy('tanggal_sampling', 'asc');

            $data->where(function ($query) use ($expiredNamaLabs) {
                foreach ($expiredNamaLabs as $namaLab) {
                    $query->orWhereRaw("JSON_SEARCH(parameter, 'one', '%{$namaLab}%') IS NOT NULL");
                }
            });

            $data = $data->get();
        }

        return DataTables::of($data)
            ->editColumn('tanggal_sampling', function ($row) {
                return Carbon::parse($row->tanggal_sampling)->format('Y-m-d');
            })
            ->make(true);
    }

    public function parameterData(Request $request)
    {
        $id_regulasi = explode('-', $request->regulasi)[0];
        $data = MasterBakumutu::where('id_regulasi', $id_regulasi)
            ->where('is_active', 1)
            ->get();

        return response()->json([
            'data' => $data
        ], 200);
        //  return DataTables::of($data)->make(true);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', 1)->get();
        return response()->json($data);
    }

    public function getSubKategori(Request $request)
    {
        $data = MasterSubKategori::where('id_kategori', $request->id_kategori)->get();
        return response()->json($data);
    }

    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::with(['bakumutu'])->where('id_kategori', $request->id_kategori)->get();
        return response()->json($data);
    }

    public function getParameter(Request $request)
    {
        $data = Parameter::where('id_kategori', $request->id_kategori)
            ->where('is_active', 1)
            ->get();
        return response()->json($data);
    }
    public function getParameterNonExpired(Request $request)
    {
        $param = [];
        foreach ($request->params as $value) {
            $param[] = explode(';', $value)[1];
        }
        $data = Parameter::whereIn('nama_lab', $param)
            ->where('is_active', 1)
            ->where('is_expired', 0)
            ->get();

        return response()->json($data);
    }
 public function getParameterExpired(Request $request)
    {
        $param = [];
        foreach ($request->params as $value) {
            $param[] = explode(';', $value)[1];
        }
        // dd((int)$request->kategori);
        $data = Parameter::whereIn('nama_lab', $param)
            ->where('is_active', 1)
            ->where('id_kategori',(int)$request->kategori)
            ->where('is_expired', 1)
            ->get();

        return response()->json($data);
    }

    public function updateData(Request $request)
    {
        try {
            $data = OrderDetail::where('id', $request->id)->first();
            $record = new TcOrderDetail();
            $param = $request->param[0] ?? ''; // Ambil param[0]
            $paramArray = array_map('trim', explode(',', $param)); // Pecah jadi array

            // Ambil param, existing, dan pengganti sebagai array biasa
            $params = $paramArray;
            $existing = $request->existing;
            $pengganti = $request->pengganti;
            if ($existing !== null && $pengganti !== null) {
                foreach ($params as $index => $value) {
                    $key = array_search($value, $existing);
                    if ($key !== false && isset($pengganti[$key])) {
                        $params[$index] = $pengganti[$key];
                    }
                }
            }

            $data->parameter = json_encode($params) ?? null;
            $data->regulasi = json_encode($request->regulasi) ?? null;
            $data->save();

            $record->id_order_detail = $data->id;
            $record->no_sampel = $data->no_sampel;
            $record->updated_tc_by = $this->karyawan;
            $record->updated_tc_at = Carbon::now()->format('Y-m-d H:i:s');
            $record->save();

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Data berhasil diupdate',
                'data' => $data
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine()
            ], 500);
        }
    }
}
