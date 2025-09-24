<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use App\Models\TemplateStp;
use App\Models\MasterKategori;
use App\Models\Subkontrak;
use App\Models\OrderDetail;
use App\Models\WsValueAir;
use App\Models\WsValueUdara;

use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;




class SubKontrakController extends Controller
{

    public function index(Request $request)
    {
        $data = Subkontrak::with(['ws_value', 'category'])->where('is_active', true)->where('category_id', $request->category)->where('is_approve', 0);
        return Datatables::of($data)->make(true);

    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = new Subkontrak();
            $data->no_sampel = $request->no_sampel;
            $data->category_id = $request->category;
            $data->parameter = json_encode($request->parameter);
            $data->created_at = Carbon::now();
            $data->created_by = $this->karyawan;
            $data->save();
            // dd($data);
            if ($request->category == 1) {
                $ws = new WsValueAir();
                $ws->no_sampel = $request->no_sampel;
                $ws->id_subkontrak = $data->id;
                $ws->hasil = $request->hasil;
                $ws->is_active = true;
                $ws->status = 0;
                $ws->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Data Berhasil Disimpan',
                'status' => 200,
                'success' => true
            ], 200);
        } catch (exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 500,
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function approve(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = Subkontrak::where('id', $request->id)->first();
            $data->is_approve = 1;
            $data->approved_at = Carbon::now();
            $data->approved_by = $this->karyawan;
            $data->save();
            DB::commit();
            return response()->json([
                'message' => 'Data Berhasil Disimpan',
                'status' => 200,
                'success' => true
            ], 200);
        } catch (exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 500,
                'line' => $e->getLine()
            ], 500);
        }
    }
    public function reject(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = Subkontrak::where('id', $request->id)->first();
            $data->is_reject = 1;
            $data->is_active = 0;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->save();
            DB::commit();
            return response()->json([
                'message' => 'Data Berhasil Disimpan',
                'status' => 200,
                'success' => true
            ], 200);
        } catch (exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 500,
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function getCategory()
    {
        $name = TemplateStp::where('is_active', true)->where('name', 'SUBKONTRAK')->pluck('category_id');
        // dd($name);
        if ($name->isEmpty()) {
            return response()->json([
                'message' => 'Template Subkontrak tidak ditemukan',
                'status' => 404
            ], 404);
        }
        $data = MasterKategori::where('is_active', true)->whereIn('id', $name)->get();
        // dd($data);

        return response()->json([
            'data' => $data,
            'status' => 200
        ], 200);
    }


    public function getParameter(Request $request)
    {
        $param = TemplateStp::where('is_active', true)->where('name', 'SUBKONTRAK')->where('category_id', $request->category)->select('param')->first();

        if (!$param) {
            return response()->json([
                'message' => 'Template Subkontrak tidak ditemukan',
                'status' => 404
            ], 404);
        }
        // dd($param);

        return response()->json([
            'data' => $param,
            'status' => 200
        ], 200);
    }

    public function getParamOrder(Request $request)
    {
        $param = OrderDetail::where('is_active', true)->where('no_sampel', $request->no_sampel)->first();

        if (!$param) {
            return response()->json([
                'data' => [],
                'status' => 200
            ], 404);
        } else {
            return response()->json([
                'data' => $param,
                'status' => 200
            ], 200);
        }
    }

    // Analyst Sub Kontrak
    public function indexAnalyst(Request $request)
    {
        $data = Subkontrak::with(['ws_value', 'category'])->where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function getParameters(Request $request)
    {
        $orderDetail = OrderDetail::where('is_active', true)
            ->where('no_sampel', $request->no_sampel)
            ->first();

        if (!$orderDetail) {
            return response()->json(['message' => "Tidak ada data dengan nomor sampel $request->no_sampel"], 404);
        }

        $regulasi = explode('-', $orderDetail->regulasi)[0];

        $data = WsValueAir::with([
            'gravimetri.baku_mutu',
            'titrimetri.baku_mutu',
            'colorimetri.baku_mutu',
            'subkontrak.baku_mutu',
            'subkontrak.createdByKaryawan',
            'dataLapanganAir',
        ])
        ->where('no_sampel', $request->no_sampel)
        ->where('status', 0)
        ->where(function ($query) {
            $query->whereHas('colorimetri', fn($q) => $q->where('is_approved', 1))
                ->orWhereHas('gravimetri', fn($q) => $q->where('is_approved', 1))
                ->orWhereHas('titrimetri', fn($q) => $q->where('is_approved', 1))
                ->orWhereHas('subkontrak', fn($q) => $q->where('is_approve', 1));
        })
        ->get(); // <== INI PENTING GENG!

        // dd($data);

        $allParameters = collect();

        foreach ($data as $item) {
            if (!empty($item->gravimetri)) {
                $allParameters = $allParameters->merge(collect($item->gravimetri)->pluck('parameter'));
            } elseif (!empty($item->colorimetri)) {
                $allParameters = $allParameters->merge(collect($item->colorimetri)->pluck('parameter'));
            } elseif (!empty($item->titrimetri)) {
                $allParameters = $allParameters->merge(collect($item->titrimetri)->pluck('parameter'));
            } elseif (!empty($item->subkontrak)) {
                $allParameters = $allParameters->merge(collect($item->subkontrak)->pluck('parameter'));
            }
        }

        $allParameters = $allParameters->unique()->values();

        $decoded = json_decode($orderDetail->parameter, true);
        $existingParams = collect(is_array($decoded) ? $decoded : [])
        ->map(function ($item) {
            // misal formatnya "16;As Terlarut"
            return explode(';', $item)[1] ?? $item;
        });

        $filteredTestedParams = $allParameters
        ->filter(fn($param) => !empty($param) && $param !== 'null')
        ->values();

        $missingParams = $existingParams->diff($filteredTestedParams)->values();

        return response()->json([
            'missing_parameters' => $missingParams,
            'all_parameters' => $existingParams,
            'parameters_diuji' => $allParameters
        ]);
    }

    public function storeOrUpdate(Request $request) 
    {
        try {
            if(isset($request->id)) {
                $data = Subkontrak::where('id', $request->id)->where('no_sampel', $request->no_sampel)->first();
                $data->hp = $request->hp;
                $data->fp = $request->fp;
                $data->note = $request->note;
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now();
                $data->save();

                $ws_air = WsValueAir::where('id_subkontrak', $data->id)->first();
                $ws_air->hasil = $request->hasil;
                $ws_air->save();

                return response()->json([
                    'message' => 'Data Berhasil Diperbaharui',
                    'status' => 200,
                    'success' => true
                ], 200);
            } else {
                $orderDetail = OrderDetail::where('is_active', true)->where('no_sampel', $request->no_sampel)->first();

                $data = new Subkontrak();
                $data->no_sampel = $request->no_sampel;
                $data->category_id = explode('-', $orderDetail->kategori_2)[0];
                $data->parameter = $request->parameter;
                $data->hp = $request->hp;
                $data->is_approve = true;
                $data->fp = $request->fp;
                $data->jenis_pengujian = 'Subkontrak';
                $data->note = $request->note;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now();
                $data->save();

                $ws_air = new WsValueAir();
                $ws_air->no_sampel = $request->no_sampel;
                $ws_air->id_subkontrak = $data->id;
                $ws_air->hasil = $request->hasil;
                $ws_air->save();

                return response()->json([
                    'message' => 'Data Berhasil Disimpan',
                    'status' => 200,
                    'success' => true
                ], 200);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'status' => 500,
                'line' => $th->getLine()
            ], 500);
        }
    }

    public function deleteSubKontrak(Request $request) 
    {
        try {
            $data = Subkontrak::where('id', $request->id)->first();
            $data->is_active = 0;
            $data->save();
            return response()->json([
                'message' => 'Data Berhasil Dihapus',
                'status' => 200,
                'success' => true
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'status' => 500,
                'line' => $th->getLine()
            ], 500);
        }
    }

}
